<?php

namespace Zenstruck\Messenger\Test\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Zenstruck\Messenger\Test\InteractsWithQueue;
use Zenstruck\Messenger\Test\Tests\Fixture\Messenger\MessageA;
use Zenstruck\Messenger\Test\Tests\Fixture\Messenger\MessageAHandler;
use Zenstruck\Messenger\Test\Tests\Fixture\Messenger\MessageB;
use Zenstruck\Messenger\Test\Tests\Fixture\Messenger\MessageBHandler;
use Zenstruck\Messenger\Test\Tests\Fixture\Messenger\MessageC;
use Zenstruck\Messenger\Test\Tests\Fixture\NoBundleKernel;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class InteractsWithQueueTest extends WebTestCase
{
    use InteractsWithQueue;

    /**
     * @test
     */
    public function can_interact_with_queue(): void
    {
        self::bootKernel();

        $this->queue()->assertEmpty();
        $this->assertEmpty(self::$container->get(MessageAHandler::class)->messages);
        $this->assertEmpty(self::$container->get(MessageBHandler::class)->messages);

        self::$container->get(MessageBusInterface::class)->dispatch(new MessageA());
        self::$container->get(MessageBusInterface::class)->dispatch(new MessageB());
        self::$container->get(MessageBusInterface::class)->dispatch(new MessageA());

        $this->queue()->assertCount(3);
        $this->queue()->assertPushed(MessageA::class);
        $this->queue()->assertPushed(MessageA::class, 2);
        $this->queue()->assertPushed(MessageB::class);
        $this->queue()->assertPushed(MessageB::class, 1);
        $this->queue()->assertNotPushed(MessageC::class);
        $this->assertEmpty(self::$container->get(MessageAHandler::class)->messages);
        $this->assertEmpty(self::$container->get(MessageBHandler::class)->messages);

        $this->queue()->process(2);

        $this->queue()->assertCount(1);
        $this->queue()->assertPushed(MessageA::class, 1);
        $this->queue()->assertNotPushed(MessageB::class);
        $this->assertCount(1, self::$container->get(MessageAHandler::class)->messages);
        $this->assertCount(1, self::$container->get(MessageBHandler::class)->messages);

        $this->queue()->process();

        $this->queue()->assertEmpty();
        $this->queue()->assertNotPushed(MessageA::class);
        $this->queue()->assertNotPushed(MessageB::class);
        $this->assertCount(2, self::$container->get(MessageAHandler::class)->messages);
        $this->assertCount(1, self::$container->get(MessageBHandler::class)->messages);
    }

    /**
     * @test
     */
    public function can_disable_intercept(): void
    {
        self::bootKernel();

        $this->queue()->unblock();

        $this->queue()->assertEmpty();

        self::$container->get(MessageBusInterface::class)->dispatch(new MessageA());
        self::$container->get(MessageBusInterface::class)->dispatch(new MessageB());
        self::$container->get(MessageBusInterface::class)->dispatch(new MessageA());

        $this->queue()->assertEmpty();
        $this->queue()->assertNotPushed(MessageA::class);
        $this->assertCount(2, self::$container->get(MessageAHandler::class)->messages);
        $this->assertCount(1, self::$container->get(MessageBHandler::class)->messages);
    }

    /**
     * @test
     */
    public function disabling_intercept_with_items_on_queue_processes_all(): void
    {
        self::bootKernel();

        self::$container->get(MessageBusInterface::class)->dispatch(new MessageA());
        self::$container->get(MessageBusInterface::class)->dispatch(new MessageB());
        self::$container->get(MessageBusInterface::class)->dispatch(new MessageA());

        $this->queue()->assertCount(3);

        $this->queue()->process();

        $this->queue()->assertEmpty();
        $this->assertCount(2, self::$container->get(MessageAHandler::class)->messages);
        $this->assertCount(1, self::$container->get(MessageBHandler::class)->messages);
    }

    /**
     * @test
     */
    public function cannot_access_queue_if_none_registered(): void
    {
        self::bootKernel(['environment' => 'test']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('No transports registered');

        $this->queue();
    }

    /**
     * @test
     */
    public function cannot_access_queue_if_kernel_not_booted(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('kernel must be booted');

        $this->queue();
    }

    /**
     * @test
     */
    public function can_interact_with_multiple_queues(): void
    {
        self::bootKernel(['environment' => 'multi_transport']);

        $this->queue('async1')->assertEmpty();
        $this->queue('async2')->assertEmpty();
        $this->assertEmpty(self::$container->get(MessageAHandler::class)->messages);
        $this->assertEmpty(self::$container->get(MessageBHandler::class)->messages);

        self::$container->get(MessageBusInterface::class)->dispatch(new MessageA());
        self::$container->get(MessageBusInterface::class)->dispatch(new MessageB());
        self::$container->get(MessageBusInterface::class)->dispatch(new MessageA());

        $this->queue('async1')->assertCount(2);
        $this->queue('async2')->assertEmpty();
        $this->queue('async1')->assertPushed(MessageA::class);
        $this->queue('async1')->assertPushed(MessageA::class, 2);
        $this->queue('async2')->assertNotPushed(MessageB::class);
        $this->assertEmpty(self::$container->get(MessageAHandler::class)->messages);
        $this->assertCount(1, self::$container->get(MessageBHandler::class)->messages);

        $this->queue('async1')->process(1);
        $this->queue('async2')->process(1);

        $this->queue('async1')->assertCount(1);
        $this->queue('async1')->assertPushed(MessageA::class, 1);
        $this->queue('async2')->assertNotPushed(MessageB::class);
        $this->assertCount(1, self::$container->get(MessageAHandler::class)->messages);
        $this->assertCount(1, self::$container->get(MessageBHandler::class)->messages);

        $this->queue('async1')->process();
        $this->queue('async2')->process();

        $this->queue('async1')->assertEmpty();
        $this->queue('async2')->assertEmpty();
        $this->assertCount(2, self::$container->get(MessageAHandler::class)->messages);
        $this->assertCount(1, self::$container->get(MessageBHandler::class)->messages);
    }

    /**
     * @test
     */
    public function can_enable_intercept(): void
    {
        self::bootKernel(['environment' => 'multi_transport']);

        $this->queue('async2')->intercept();

        $this->queue('async2')->assertEmpty();
        $this->assertEmpty(self::$container->get(MessageBHandler::class)->messages);

        self::$container->get(MessageBusInterface::class)->dispatch(new MessageB());
        self::$container->get(MessageBusInterface::class)->dispatch(new MessageB());

        $this->queue('async2')->assertCount(2);
    }

    /**
     * @test
     */
    public function cannot_access_queue_that_does_not_exist(): void
    {
        self::bootKernel(['environment' => 'multi_transport']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"invalid" not registered');

        $this->queue('invalid');
    }

    /**
     * @test
     */
    public function cannot_access_queue_that_is_not_test_transport(): void
    {
        self::bootKernel(['environment' => 'multi_transport']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Transport "async3" needs to be set to "test://" in your test config to use this feature.');

        $this->queue('async3');
    }

    /**
     * @test
     */
    public function queue_name_is_required_if_using_multiple_transports(): void
    {
        self::bootKernel(['environment' => 'multi_transport']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Multiple transports are registered (async1, async2, async3), you must specify a name.');

        $this->queue();
    }

    /**
     * @test
     */
    public function can_access_message_objects_on_queue(): void
    {
        self::bootKernel();

        self::$container->get(MessageBusInterface::class)->dispatch($m1 = new MessageA());
        self::$container->get(MessageBusInterface::class)->dispatch($m2 = new MessageB());
        self::$container->get(MessageBusInterface::class)->dispatch($m3 = new MessageA());

        $this->assertSame([$m1, $m2, $m3], $this->queue()->messages());
        $this->assertSame([$m1, $m3], $this->queue()->messages(MessageA::class));
        $this->assertSame([$m2], $this->queue()->messages(MessageB::class));
    }

    /**
     * @test
     */
    public function can_access_sent_acknowledged_and_rejected(): void
    {
        self::bootKernel();

        self::$container->get(MessageBusInterface::class)->dispatch($m1 = new MessageA(true));
        self::$container->get(MessageBusInterface::class)->dispatch($m2 = new MessageB());

        $this->assertCount(2, $this->queue()->get());
        $this->assertCount(2, $this->queue()->sent());
        $this->assertCount(0, $this->queue()->acknowledged());
        $this->assertCount(0, $this->queue()->rejected());

        $this->queue()->process();

        $this->assertCount(0, $this->queue()->get());
        $this->assertCount(2, $this->queue()->sent());
        $this->assertCount(1, $this->queue()->acknowledged());
        $this->assertCount(1, $this->queue()->rejected());
    }

    /**
     * @test
     */
    public function cannot_access_queue_if_bundle_not_enabled(): void
    {
        self::$class = NoBundleKernel::class;
        self::bootKernel(['environment' => 'no_bundle']);
        self::$class = null;

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot access queue transport - is ZenstruckMessengerTestBundle enabled in your test environment?');

        $this->queue();
    }

    protected static function bootKernel(array $options = []): KernelInterface
    {
        return parent::bootKernel(\array_merge(['environment' => 'single_transport'], $options));
    }
}
