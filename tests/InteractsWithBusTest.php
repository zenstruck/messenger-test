<?php

namespace Zenstruck\Messenger\Test\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Zenstruck\Messenger\Test\InteractsWithMessenger;
use Zenstruck\Messenger\Test\Tests\Fixture\Messenger\MessageA;
use Zenstruck\Messenger\Test\Tests\Fixture\Messenger\MessageB;
use Zenstruck\Messenger\Test\Tests\Fixture\Messenger\MessageC;

class InteractsWithBusTest extends WebTestCase
{
    use InteractsWithMessenger;

    /**
     * @test
     */
    public function can_interact_with_buses()
    {
        self::bootKernel();

        self::getContainer()->get(MessageBusInterface::class)->dispatch(new MessageA());
        $this->bus()->dispatched()
            ->assertCount(1)
            ->assertContains(MessageA::class, 1);
    }

    /**
     * @test
     */
    public function use_default_sync_transport()
    {
        self::bootKernel(['environment' => 'default_sync_transport']);

        self::getContainer()->get(MessageBusInterface::class)->dispatch(new MessageA());
        $this->bus()->dispatched()
            ->assertCount(1)
            ->assertContains(MessageA::class, 1);
    }

    /**
     * @test
     */
    public function interacts_with_specified_bus(): void
    {
        self::bootKernel(['environment' => 'multi_bus']);

        $this->bus('bus_a')->dispatched()->assertEmpty();
        $this->bus('bus_b')->dispatched()->assertEmpty();
        $this->bus('bus_c')->dispatched()->assertEmpty();

        self::getContainer()->get('bus_a')->dispatch(new MessageA(fail: true));
        self::getContainer()->get('bus_b')->dispatch(new MessageB());
        self::getContainer()->get('bus_c')->dispatch(new MessageC());

        $this->transport()
            ->process()
            ->rejected()
            ->assertContains(MessageA::class, 4)
        ;

        $this->bus('bus_a')->dispatched()->assertCount(1);
        $this->bus('bus_b')->dispatched()->assertCount(1);
        $this->bus('bus_c')->dispatched()->assertCount(1);
        $this->bus('bus_a')->dispatched()->assertContains(MessageA::class, 1);
        $this->bus('bus_b')->dispatched()->assertContains(MessageB::class, 1);
        $this->bus('bus_c')->dispatched()->assertContains(MessageC::class, 1);
    }

    protected static function bootKernel(array $options = []): KernelInterface // @phpstan-ignore-line
    {
        return parent::bootKernel(\array_merge(['environment' => 'single_transport'], $options));
    }
}
