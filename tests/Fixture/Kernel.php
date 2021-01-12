<?php

namespace Zenstruck\Messenger\Test\Tests\Fixture;

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\RouteCollectionBuilder;
use Zenstruck\Messenger\Test\Tests\Fixture\Messenger\MessageA;
use Zenstruck\Messenger\Test\ZenstruckMessengerTestBundle;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function dispatch(): Response
    {
        $this->getContainer()->get('message_bus')->dispatch(new MessageA());

        return new Response();
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new ZenstruckMessengerTestBundle();
    }

    protected function configureContainer(ContainerBuilder $c, LoaderInterface $loader): void
    {
        $loader->load(\sprintf('%s/config/%s.yaml', __DIR__, $this->getEnvironment()));
    }

    protected function configureRoutes(RouteCollectionBuilder $routes): void
    {
        $routes->add('/dispatch', 'kernel::dispatch');
    }
}
