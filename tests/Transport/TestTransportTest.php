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

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\ClaimCheckNotFoundException;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Stamp\TrustStamp;
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
    public function receiving_a_message_does_not_create_another_claim(): void
    {
        $this->requireClass(ClaimCheckSerializer::class);

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
    public function an_expired_claim_is_a_decoding_failure(): void
    {
        $this->requireClass(ClaimCheckSerializer::class);

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
    public function preserves_a_preencoded_claim_reference(): void
    {
        $this->requireClass(ClaimCheckSerializer::class);

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

    #[Test]
    public function handles_local_messages_requiring_a_signature_without_serialization(): void
    {
        $this->requireClass(TrustStamp::class);

        $handled = false;
        $handler = new HandlerDescriptor(static function(MessageA $message) use (&$handled) {
            $handled = true;
        }, ['sign' => true]);
        $bus = new MessageBus([new HandleMessageMiddleware(new HandlersLocator([MessageA::class => [$handler]]))]);
        $transport = $this->createTransport(options: ['test_serialization' => false], bus: $bus);
        $transport->send(new MessageA());

        $transport->process();

        self::assertTrue($handled);
        $acknowledged = $transport->acknowledged()->first()->envelope;
        self::assertTrue(TrustStamp::isEnvelopeTrusted($acknowledged));
    }

    #[Test]
    public function preserves_explicitly_untrusted_messages_without_serialization(): void
    {
        $this->requireClass(TrustStamp::class);

        $transport = $this->createTransport(options: ['test_serialization' => false]);
        $stamp = TrustStamp::untrusted();
        $transport->send(new Envelope(new MessageA(), [$stamp]));

        $received = \iterator_to_array($transport->get())[0];

        self::assertSame($stamp, $received->last(TrustStamp::class));
        self::assertFalse(TrustStamp::isEnvelopeTrusted($received));
    }

    #[Test]
    public function does_not_trust_messages_received_elsewhere_without_serialization(): void
    {
        $this->requireClass(TrustStamp::class);

        $transport = $this->createTransport(options: ['test_serialization' => false]);
        $transport->send(new Envelope(new MessageA(), [new ReceivedStamp('external')]));

        $received = \iterator_to_array($transport->get())[0];

        self::assertFalse(TrustStamp::isEnvelopeTrusted($received));
    }

    #[Test]
    public function does_not_trust_preencoded_messages_without_serialization(): void
    {
        $this->requireClass(TrustStamp::class);

        $transport = $this->createTransport(options: ['test_serialization' => false]);
        $transport->send((new PhpSerializer())->encode(new Envelope(new MessageA())));

        $received = \iterator_to_array($transport->get())[0];
        $received = $received->with(new ReceivedStamp('async'));

        self::assertFalse(TrustStamp::isEnvelopeTrusted($received));
    }

    #[Test]
    public function can_find_and_remove_a_queued_message(): void
    {
        $transport = $this->createTransport();
        $first = $transport->send(new MessageA());
        $second = $transport->send(new MessageA(true));
        $firstId = $first->last(TransportMessageIdStamp::class)->getId();
        $secondId = $second->last(TransportMessageIdStamp::class)->getId();

        self::assertNotSame($firstId, $secondId);
        self::assertFalse($transport->find((string) $firstId)->getMessage()->fail);

        $transport->reject($transport->find($firstId));

        self::assertNull($transport->find($firstId));
        self::assertSame(1, $transport->getMessageCount());
        self::assertTrue($transport->find($secondId)->getMessage()->fail);

        $transport->ack($transport->find($secondId));

        self::assertSame(0, $transport->getMessageCount());
        self::assertSame(1, $transport->rejected()->count());
        self::assertSame(1, $transport->acknowledged()->count());
    }

    #[Test]
    public function acknowledging_a_delivery_does_not_remove_its_retry(): void
    {
        $transport = $this->createTransport();
        $transport->send(new MessageA());
        $delivered = \iterator_to_array($transport->get())[0];
        $retry = $transport->send($delivered);

        $transport->ack($delivered);

        self::assertSame(1, $transport->getMessageCount());
        $retryId = $retry->last(TransportMessageIdStamp::class)->getId();
        self::assertNotSame($delivered->last(TransportMessageIdStamp::class)->getId(), $retryId);
        self::assertNotNull($transport->find($retryId));
    }

    #[Test]
    public function acknowledging_a_message_from_before_reset_does_not_remove_a_new_message(): void
    {
        $transport = $this->createTransport();
        $old = $transport->send(new MessageA());
        $transport->reset();
        $transport->send(new MessageA(true));

        $transport->ack($old);

        self::assertSame(1, $transport->getMessageCount());
        self::assertTrue(\iterator_to_array($transport->get())[0]->getMessage()->fail);
    }

    #[Test]
    public function unknown_and_invalid_message_ids_are_not_found(): void
    {
        $transport = $this->createTransport();
        $transport->send(new MessageA());

        self::assertNull($transport->find('missing'));
        self::assertNull($transport->find([]));
        self::assertNull($transport->find(null));
    }

    #[Test]
    public function listing_and_finding_expired_claims_preserve_the_transport_id(): void
    {
        $this->requireClass(ClaimCheckSerializer::class);

        $pool = new ArrayAdapter();
        $transport = $this->createTransport(new ClaimCheckSerializer(new PhpSerializer(), $pool, 1024));
        $sent = $transport->send((object) ['body' => \str_repeat('x', 5000)]);
        $id = $sent->last(TransportMessageIdStamp::class)->getId();
        $pool->clear();

        $listed = \iterator_to_array($transport->all())[0];
        $found = $transport->find($id);

        self::assertInstanceOf(MessageDecodingFailedException::class, $listed->getMessage());
        self::assertInstanceOf(MessageDecodingFailedException::class, $found->getMessage());
        self::assertSame($id, $listed->last(TransportMessageIdStamp::class)->getId());
        self::assertSame($id, $found->last(TransportMessageIdStamp::class)->getId());
        $transport->reject($found);
        self::assertSame(0, $transport->getMessageCount());
    }

    private function requireClass(string $class): void
    {
        if (!\class_exists($class)) {
            self::markTestSkipped('Symfony 8.2 is required.');
        }
    }

    /**
     * @param array<string, bool> $options
     */
    private function createTransport(?SerializerInterface $serializer = null, array $options = [], ?MessageBusInterface $bus = null): TestTransport
    {
        return new TestTransport('async', $bus ?? new MessageBus(), new EventDispatcher(), $serializer ?? new PhpSerializer(), new MockClock(), $options + [
            'support_delay_stamp' => true,
            'impacts_assertions_count' => false,
        ]);
    }
}
