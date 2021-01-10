<?php

namespace Zenstruck\Messenger\Test;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Zenstruck\Messenger\Test\Transport\TestTransportFactory;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class ZenstruckMessengerTestBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $definition = new Definition(TestTransportFactory::class, [new Reference('messenger.routable_message_bus')]);
        $definition
            ->setPublic(true)
            ->addTag('messenger.transport_factory')
            ->addTag('kernel.reset', ['method' => 'reset'])
        ;

        $container->setDefinition(TestTransportFactory::class, $definition);
    }

    public function getContainerExtension(): ?ExtensionInterface
    {
        return null;
    }
}
