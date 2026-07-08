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

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Zenstruck\Messenger\Test\Bus\TestBus;
use Zenstruck\Messenger\Test\Bus\TestBusRegistry;

class TestBusRegistryTest extends TestCase
{
    #[Test]
    public function get_default_bus()
    {
        $registry = new TestBusRegistry();
        $registry->register('bus-a', $bus = new TestBus('bus-a', $this->createStub(MessageBusInterface::class)));

        self::assertSame($bus, $registry->get());
    }

    #[Test]
    public function get_named_bus(): void
    {
        $registry = new TestBusRegistry();
        $registry->register('bus-a', new TestBus('bus-a', $this->createStub(MessageBusInterface::class)));
        $registry->register('bus-b', $bus = new TestBus('bus-b', $this->createStub(MessageBusInterface::class)));

        self::assertSame($bus, $registry->get('bus-b'));
    }

    #[Test]
    public function no_buses_configured(): void
    {
        $registry = new TestBusRegistry();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('No bus registered.');

        $registry->get();
    }

    #[Test]
    public function bus_name_is_required_if_multiple_buses(): void
    {
        $registry = new TestBusRegistry();
        $registry->register('bus-a', $this->createStub(MessageBusInterface::class));
        $registry->register('bus-b', $this->createStub(MessageBusInterface::class));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Multiple buses are registered (bus-a, bus-b), you must specify a name.');

        $registry->get();
    }

    #[Test]
    public function invalid_bus_name()
    {
        $registry = new TestBusRegistry();
        $registry->register('bus-a', $this->createStub(MessageBusInterface::class));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Bus "unknown" not registered.');

        $registry->get('unknown');
    }

    #[Test]
    public function valid_decorated_bus(): void
    {
        $registry = new TestBusRegistry();
        $registry->register('bus-a', $this->createStub(MessageBusInterface::class));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Bus "bus-a" needs to be a decorator of the bus.');

        $registry->get('bus-a');
    }
}
