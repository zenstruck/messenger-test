<?php

/*
 * This file is part of the zenstruck/messenger-test package.
 *
 * (c) Kevin Bond <kevinbond@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Zenstruck\Messenger\Test;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Messenger\Test\Bus\TestBus;
use Zenstruck\Messenger\Test\Bus\TestBusRegistry;
use Zenstruck\Messenger\Test\Transport\TestTransport;
use Zenstruck\Messenger\Test\Transport\TestTransportRegistry;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
trait InteractsWithMessenger
{
    /**
     * @internal
     *
     * @before
     */
    final protected static function _initializeTestTransports(): void
    {
        TestTransport::initialize();
    }

    /**
     * @internal
     *
     * @before
     */
    final protected static function _enableMessagesCollection(): void
    {
        TestTransport::enableMessagesCollection();
        TestBus::enableMessagesCollection();
    }

    /**
     * @internal
     *
     * @after
     */
    final protected static function _disableMessagesCollection(): void
    {
        TestTransport::disableMessagesCollection();
        TestBus::disableMessagesCollection();
    }

    /**
     * @internal
     *
     * @after
     */
    final protected static function _resetMessengerTransports(): void
    {
        TestTransport::resetAll();
        TestBus::resetAll();
    }

    /**
     * @deprecated use transport() instead
     */
    final protected function messenger(?string $transport = null): TestTransport
    {
        trigger_deprecation('zenstruck/messenger-test', '1.7.0', '"messenger()" method is deprecated and will be removed in 2.0. Please use "transport()" instead.');

        return $this->transport($transport);
    }

    final protected function transport(?string $transport = null): TestTransport
    {
        $this->init();
        $container = self::getContainer();

        if (!$container->has('zenstruck_messenger_test.transport_registry')) {
            throw new \LogicException('Cannot access transport - is ZenstruckMessengerTestBundle enabled in your test environment?');
        }

        /** @var TestTransportRegistry $registry */
        $registry = $container->get('zenstruck_messenger_test.transport_registry');

        return $registry->get($transport);
    }

    final protected function bus(?string $bus = null): TestBus
    {
        $this->init();
        $container = self::getContainer();

        if (!$container->has('zenstruck_messenger_test.bus_registry')) {
            throw new \LogicException('Cannot access bus - is ZenstruckMessengerTestBundle enabled in your test environment?');
        }

        /** @var TestBusRegistry $registry */
        $registry = $container->get('zenstruck_messenger_test.bus_registry');

        return $registry->get($bus);
    }

    private function init(): void
    {
        if (!$this instanceof KernelTestCase) {
            throw new \LogicException(\sprintf('The %s trait can only be used with %s.', __TRAIT__, KernelTestCase::class));
        }

        if (!self::$booted) {
            self::bootKernel();
        }
    }
}
