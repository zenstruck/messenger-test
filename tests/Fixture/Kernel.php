<?php

/*
 * This file is part of the zenstruck/messenger-test package.
 *
 * (c) Kevin Bond <kevinbond@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Zenstruck\Messenger\Test\Tests\Fixture;

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
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

        if (class_exists(Clock::class) && !$c->has(\Psr\Clock\ClockInterface::class)) {
            $c->register(\Psr\Clock\ClockInterface::class, Clock::class)->setPublic(true);
        }
    }

    /**
     * @param RouteCollectionBuilder|RoutingConfigurator $routes
     */
    protected function configureRoutes($routes): void // @phpstan-ignore-line
    {
        if ($routes instanceof RouteCollectionBuilder) { // @phpstan-ignore-line
            $routes->add('/dispatch', 'kernel::dispatch'); // @phpstan-ignore-line

            return;
        }

        $routes->add('dispatch', '/dispatch')->controller('kernel::dispatch');
    }
}
