<?php

namespace Zenstruck\Messenger\Test;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
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
     * @after
     */
    final protected static function _resetMessengerTransports(): void
    {
        TestTransport::resetAll();
    }

    final protected function messenger(?string $transport = null): TestTransport
    {
        if (!$this instanceof KernelTestCase) {
            throw new \LogicException(\sprintf('The %s trait can only be used with %s.', __TRAIT__, KernelTestCase::class));
        }

        if (!self::$booted) {
            self::bootKernel();
        }

        $container = \method_exists($this, 'getContainer') ? self::getContainer() : self::$container;

        if (!$container->has('zenstruck_messenger_test.transport_registry')) {
            throw new \LogicException('Cannot access transport - is ZenstruckMessengerTestBundle enabled in your test environment?');
        }

        /** @var TestTransportRegistry $registry */
        $registry = $container->get('zenstruck_messenger_test.transport_registry');

        return $registry->get($transport);
    }
}
