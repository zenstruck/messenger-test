<?php

namespace Zenstruck\Messenger\Test;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Messenger\Test\Transport\TestTransport;
use Zenstruck\Messenger\Test\Transport\TestTransportFactory;
use Zenstruck\Messenger\Test\Transport\TestTransportRegistry;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
trait InteractsWithMessenger
{
    /**
     * @internal
     * @after
     */
    final protected function _resetTransports(): void
    {
        TestTransportFactory::reset();
    }

    final protected function messenger(?string $transport = null): TestTransport
    {
        if (!$this instanceof KernelTestCase) {
            throw new \LogicException(\sprintf('The %s trait can only be used with %s.', __TRAIT__, KernelTestCase::class));
        }

        if (!self::$container) {
            self::bootKernel();
        }

        if (!self::$container->has(TestTransportRegistry::class)) {
            throw new \LogicException('Cannot access transport - is ZenstruckMessengerTestBundle enabled in your test environment?');
        }

        return self::$container->get(TestTransportRegistry::class)->get($transport);
    }
}
