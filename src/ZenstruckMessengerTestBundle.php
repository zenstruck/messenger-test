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

use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Zenstruck\Messenger\Test\Bus\TestBus;
use Zenstruck\Messenger\Test\Bus\TestBusRegistry;
use Zenstruck\Messenger\Test\Retry\DisableableRetryStrategy;
use Zenstruck\Messenger\Test\Transport\TestTransportFactory;
use Zenstruck\Messenger\Test\Transport\TestTransportRegistry;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class ZenstruckMessengerTestBundle extends Bundle implements CompilerPassInterface
{
    public function build(ContainerBuilder $container): void
    {
        $container->register('zenstruck_messenger_test.transport_factory', TestTransportFactory::class)
            ->setArguments([
                new Reference('messenger.routable_message_bus'),
                new Reference('event_dispatcher'),
                new Reference(ClockInterface::class, invalidBehavior: ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->addTag('messenger.transport_factory')
        ;

        $container->register('zenstruck_messenger_test.transport_registry', TestTransportRegistry::class)
            ->setPublic(true)
        ;

        $container->register('zenstruck_messenger_test.bus_registry', TestBusRegistry::class)
            ->setPublic(true)
        ;

        $container->addCompilerPass($this);
    }

    public function getContainerExtension(): ?ExtensionInterface
    {
        return null;
    }

    public function process(ContainerBuilder $container): void
    {
        $transportRegistry = $container->getDefinition('zenstruck_messenger_test.transport_registry');

        foreach ($container->findTaggedServiceIds('messenger.receiver') as $id => $tags) {
            $name = $id;
            $transport = $container->getDefinition($name);

            if (!$class = $transport->getClass()) {
                continue;
            }

            if (!\is_a($class, TransportInterface::class, true)) {
                continue;
            }

            $transport->addTag('kernel.reset', ['method' => 'resetOnKernelShutdown', 'on_invalid' => 'ignore']);

            foreach ($tags as $tag) {
                if (isset($tag['alias'])) {
                    $name = $tag['alias'];
                }
            }

            $transportRegistry->addMethodCall('register', [$name, new Reference($id)]);
        }

        $busRegistry = $container->getDefinition('zenstruck_messenger_test.bus_registry');

        foreach ($container->findTaggedServiceIds('messenger.bus') as $id => $tags) {
            $name = "{$id}.test-bus";
            $busRegistry->addMethodCall('register', [$id, new Reference($name)]);
            $container->register($name, TestBus::class)
                ->setAutowired(true)
                ->setPublic(true)
                ->setArgument('$name', $id)
                ->setDecoratedService($id)
            ;
        }

        $this->decorateRetryStrategies($container);
    }

    /**
     * Wraps each transport's retry strategy with a {@see DisableableRetryStrategy} so retries can be
     * toggled at runtime per transport (via TestTransport::enableRetries()/disableRetries()).
     */
    private function decorateRetryStrategies(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('messenger.retry_strategy_locator')) {
            return;
        }

        $locator = $container->getDefinition('messenger.retry_strategy_locator');
        $strategies = $locator->getArgument(0);

        if (!\is_array($strategies)) {
            return;
        }

        foreach ($strategies as $name => $strategy) {
            // the locator arguments are plain References at this point (before ServiceLocatorTagPass),
            // but handle the wrapped form too in case pass ordering ever changes
            $inner = $strategy instanceof ServiceClosureArgument ? $strategy->getValues()[0] : $strategy;

            $container->register($id = "zenstruck_messenger_test.retry_strategy.{$name}", DisableableRetryStrategy::class)
                ->setArguments([$inner, $name])
            ;

            $strategies[$name] = $strategy instanceof ServiceClosureArgument
                ? new ServiceClosureArgument(new Reference($id))
                : new Reference($id);
        }

        $locator->replaceArgument(0, $strategies);
    }
}
