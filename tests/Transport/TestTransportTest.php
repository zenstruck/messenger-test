<?php

/*
 * This file is part of the zenstruck/messenger-test package.
 *
 * (c) Kevin Bond <kevinbond@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Zenstruck\Messenger\Test\Tests\Transport;

use PHPUnit\Framework\Attributes\RequiresClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\ClaimCheckNotFoundException;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Transport\Serialization\ClaimCheckSerializer;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Zenstruck\Messenger\Test\Tests\Fixture\Messenger\MessageA;
use Zenstruck\Messenger\Test\Transport\TestTransport;

final class TestTransportTest extends TestCase
{
    protected function setUp(): void
    {
        TestTransport::resetAll();
        TestTransport::enableMessagesCollection();
    }

    protected function tearDown(): void
    {
        TestTransport::resetAll();
        TestTransport::disableMessagesCollection();
    }

    #[Test]
    public function receives_the_message_as_it_was_when_sent(): void
    {
        $transport = $this->createTransport();
        $message = new MessageA();
        $transport->send($message);
        $message->fail = true;

        $received = \iterator_to_array($transport->get())[0];

        self::assertFalse($received->getMessage()->fail);
        self::assertNotSame($message, $received->getMessage());
    }

    #[Test]
    #[RequiresClass(ClaimCheckSerializer::class)]
    public function receiving_a_message_does_not_create_another_claim(): void
    {
        $pool = new ArrayAdapter();
        $serializer = new ClaimCheckSerializer(new PhpSerializer(), $pool, 1024);
        $transport = $this->createTransport($serializer);
        $message = (object) ['body' => \str_repeat('x', 5000)];
        $transport->send($message);
        self::assertCount(1, $pool->getValues());

        $received = \iterator_to_array($transport->get())[0];

        self::assertEquals($message, $received->getMessage());
        self::assertCount(1, $pool->getValues());
    }

    #[Test]
    #[RequiresClass(ClaimCheckSerializer::class)]
    public function an_expired_claim_is_a_decoding_failure(): void
    {
        $pool = new ArrayAdapter();
        $serializer = new ClaimCheckSerializer(new PhpSerializer(), $pool, 1024);
        $transport = $this->createTransport($serializer);
        $transport->send((object) ['body' => \str_repeat('x', 5000)]);
        $pool->clear();

        $received = \iterator_to_array($transport->get())[0];

        self::assertInstanceOf(MessageDecodingFailedException::class, $received->getMessage());
        self::assertInstanceOf(ClaimCheckNotFoundException::class, $received->getMessage()->getPrevious());
        self::assertSame([], \array_filter($pool->getValues()));
    }

    #[Test]
    #[RequiresClass(ClaimCheckSerializer::class)]
    public function preserves_a_preencoded_claim_reference(): void
    {
        $pool = new ArrayAdapter();
        $serializer = new ClaimCheckSerializer(new PhpSerializer(), $pool, 1024);
        $transport = $this->createTransport($serializer);
        $encoded = $serializer->encode(new Envelope((object) ['body' => \str_repeat('x', 5000)]));
        $transport->send($encoded);
        self::assertCount(1, $pool->getValues());
        $pool->clear();

        $received = \iterator_to_array($transport->get())[0];

        self::assertInstanceOf(MessageDecodingFailedException::class, $received->getMessage());
        self::assertSame([], \array_filter($pool->getValues()));
    }

    /**
     * @param array<string, bool> $options
     */
    private function createTransport(?SerializerInterface $serializer = null, array $options = []): TestTransport
    {
        return new TestTransport('async', new MessageBus(), new EventDispatcher(), $serializer ?? new PhpSerializer(), new MockClock(), $options + [
            'support_delay_stamp' => true,
            'impacts_assertions_count' => false,
        ]);
    }
}
