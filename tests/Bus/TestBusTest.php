<?php

/*
 * This file is part of the zenstruck/messenger-test package.
 *
 * (c) Kevin Bond <kevinbond@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Zenstruck\Messenger\Test\Tests\Bus;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Zenstruck\Messenger\Test\Bus\TestBus;

class TestBusTest extends TestCase
{
    /**
     * @test
     */
    public function collect_messages_by_default(): void
    {
        $bus = new TestBus('bus', $mock = $this->createMock(MessageBusInterface::class));
        $message = new \stdClass();
        $mock->expects(self::once())->method('dispatch')
            ->with(self::isInstanceOf(\stdClass::class), [])
            ->willReturn(new Envelope($message))
        ;

        $bus->dispatch($message);

        $bus->dispatched()->assertCount(1);
        $bus->dispatched()->assertContains(\stdClass::class, 1);
    }

    /**
     * @test
     */
    public function collect_messages_when_enabled(): void
    {
        $bus = new TestBus('bus', $testableBus = new TestableBus());

        $bus->dispatch(new \stdClass(), [$this->createMock(StampInterface::class)]);
        TestBus::disableMessagesCollection();
        $bus->dispatch(new \stdClass(), [$this->createMock(StampInterface::class)]);

        $bus->dispatched()->assertCount(1);
        self::assertCount(2, $testableBus->envelopes);
    }

    /**
     * @test
     */
    public function reset_messages(): void
    {
        $bus = new TestBus('bus', $mock = $this->createMock(MessageBusInterface::class));
        $mock->method('dispatch')->willReturn(new Envelope(new \stdClass()));

        $bus->dispatch(new \stdClass());

        TestBus::resetAll();
        $bus->dispatched()->assertEmpty();
    }
}

final class TestableBus implements MessageBusInterface
{
    public array $envelopes = [];

    public function dispatch(object $message, array $stamps = []): Envelope
    {
        $this->envelopes[] = $envelope = Envelope::wrap($message, $stamps);

        return $envelope;
    }
}
