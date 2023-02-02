<?php

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
            ->willReturn(new Envelope($message));

        $bus->dispatch($message);

        $bus->dispatched()->assertCount(1);
        $bus->dispatched()->assertContains(\stdClass::class, 1);
    }

    /**
     * @test
     */
    public function collect_messages_when_enabled(): void
    {
        $bus = new TestBus('bus', $mock = $this->createMock(MessageBusInterface::class));
        $stamps = [$this->createMock(StampInterface::class)];
        $mock->expects(self::exactly(2))->method('dispatch')
            ->withConsecutive(
                [self::isInstanceOf(\stdClass::class), $stamps],
                [self::isInstanceOf(\stdClass::class), $stamps],
            )
            ->willReturnOnConsecutiveCalls(
                new Envelope(new \stdClass(), $stamps),
                new Envelope(new \stdClass(), $stamps),
            );

        $bus->dispatch(new \stdClass(), $stamps);
        TestBus::disableMessagesCollection();
        $bus->dispatch(new \stdClass(), $stamps);

        $bus->dispatched()->assertCount(1);
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
