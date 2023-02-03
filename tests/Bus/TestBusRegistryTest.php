<?php

namespace Zenstruck\Messenger\Test\Tests\Bus;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Zenstruck\Messenger\Test\Bus\TestBus;
use Zenstruck\Messenger\Test\Bus\TestBusRegistry;

class TestBusRegistryTest extends TestCase
{
    /**
     * @test
     */
    public function get_default_bus()
    {
        $registry = new TestBusRegistry();
        $registry->register('bus-a', $bus = new TestBus('bus-a', $this->createMock(MessageBusInterface::class)));

        self::assertSame($bus, $registry->get());
    }

    /**
     * @test
     */
    public function get_named_bus(): void
    {
        $registry = new TestBusRegistry();
        $registry->register('bus-a.test-bus', new TestBus('bus-a', $this->createMock(MessageBusInterface::class)));
        $registry->register('bus-b.test-bus', $bus = new TestBus('bus-b', $this->createMock(MessageBusInterface::class)));

        self::assertSame($bus, $registry->get('bus-b'));
    }

    /**
     * @test
     */
    public function no_buses_configured(): void
    {
        $registry = new TestBusRegistry();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('No bus registered.');

        $registry->get();
    }

    /**
     * @test
     */
    public function bus_name_is_required_if_multiple_buses(): void
    {
        $registry = new TestBusRegistry();
        $registry->register('bus-a', $this->createMock(MessageBusInterface::class));
        $registry->register('bus-b', $this->createMock(MessageBusInterface::class));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Multiple buses are registered (bus-a, bus-b), you must specify a name.');

        $registry->get();
    }

    /**
     * @test
     */
    public function invalid_bus_name()
    {
        $registry = new TestBusRegistry();
        $registry->register('bus-a', $this->createMock(MessageBusInterface::class));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Bus "unknown.test-bus" not registered.');

        $registry->get('unknown');
    }

    /**
     * @test
     */
    public function valid_decorated_bus(): void
    {
        $registry = new TestBusRegistry();
        $registry->register('bus-a.test-bus', $this->createMock(MessageBusInterface::class));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Bus "bus-a.test-bus" needs to be a decorator of the bus.');

        $registry->get('bus-a');
    }
}
