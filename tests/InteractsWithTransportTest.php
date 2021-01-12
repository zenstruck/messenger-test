<?php

namespace Zenstruck\Messenger\Test\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Zenstruck\Messenger\Test\InteractsWithTransport;
use Zenstruck\Messenger\Test\Tests\Fixture\Messenger\MessageA;
use Zenstruck\Messenger\Test\Tests\Fixture\Messenger\MessageAHandler;
use Zenstruck\Messenger\Test\Tests\Fixture\Messenger\MessageB;
use Zenstruck\Messenger\Test\Tests\Fixture\Messenger\MessageBHandler;
use Zenstruck\Messenger\Test\Tests\Fixture\Messenger\MessageC;
use Zenstruck\Messenger\Test\Tests\Fixture\NoBundleKernel;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class InteractsWithTransportTest extends WebTestCase
{
    use InteractsWithTransport;

    /**
     * @test
     */
    public function can_interact_with_queue(): void
    {
        self::bootKernel();

        $this->transport()->queue()->assertEmpty();
        $this->assertEmpty(self::$container->get(MessageAHandler::class)->messages);
        $this->assertEmpty(self::$container->get(MessageBHandler::class)->messages);

        self::$container->get(MessageBusInterface::class)->dispatch(new MessageA());
        self::$container->get(MessageBusInterface::class)->dispatch(new MessageB());
        self::$container->get(MessageBusInterface::class)->dispatch(new MessageA());

        $this->transport()->queue()->assertCount(3);
        $this->transport()->queue()->assertContains(MessageA::class);
        $this->transport()->queue()->assertContains(MessageA::class, 2);
        $this->transport()->queue()->assertContains(MessageB::class);
        $this->transport()->queue()->assertContains(MessageB::class, 1);
        $this->transport()->queue()->assertNotContains(MessageC::class);
        $this->assertEmpty(self::$container->get(MessageAHandler::class)->messages);
        $this->assertEmpty(self::$container->get(MessageBHandler::class)->messages);

        $this->transport()->process(2);

        $this->transport()->queue()->assertCount(1);
        $this->transport()->queue()->assertContains(MessageA::class, 1);
        $this->transport()->queue()->assertNotContains(MessageB::class);
        $this->assertCount(1, self::$container->get(MessageAHandler::class)->messages);
        $this->assertCount(1, self::$container->get(MessageBHandler::class)->messages);

        $this->transport()->process();

        $this->transport()->queue()->assertEmpty();
        $this->transport()->queue()->assertNotContains(MessageA::class);
        $this->transport()->queue()->assertNotContains(MessageB::class);
        $this->assertCount(2, self::$container->get(MessageAHandler::class)->messages);
        $this->assertCount(1, self::$container->get(MessageBHandler::class)->messages);
    }

    /**
     * @test
     */
    public function can_disable_intercept(): void
    {
        self::bootKernel();

        $this->transport()->unblock();

        $this->transport()->queue()->assertEmpty();
        $this->transport()->acknowledged()->assertEmpty();
        $this->transport()->sent()->assertEmpty();

        self::$container->get(MessageBusInterface::class)->dispatch(new MessageA());
        self::$container->get(MessageBusInterface::class)->dispatch(new MessageB());
        self::$container->get(MessageBusInterface::class)->dispatch(new MessageA());

        $this->transport()->queue()->assertEmpty();
        $this->transport()->queue()->assertNotContains(MessageA::class);
        $this->transport()->sent()->assertCount(3);
        $this->transport()->sent()->assertContains(MessageA::class, 2);
        $this->transport()->sent()->assertContains(MessageB::class, 1);
        $this->transport()->acknowledged()->assertCount(3);
        $this->transport()->acknowledged()->assertContains(MessageA::class, 2);
        $this->transport()->acknowledged()->assertContains(MessageB::class, 1);
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

        $this->transport()->queue()->assertCount(3);

        $this->transport()->process();

        $this->transport()->queue()->assertEmpty();
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

        $this->transport();
    }

    /**
     * @test
     */
    public function cannot_access_queue_if_kernel_not_booted(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('kernel must be booted');

        $this->transport();
    }

    /**
     * @test
     */
    public function can_interact_with_multiple_queues(): void
    {
        self::bootKernel(['environment' => 'multi_transport']);

        $this->transport('async1')->queue()->assertEmpty();
        $this->transport('async2')->queue()->assertEmpty();
        $this->assertEmpty(self::$container->get(MessageAHandler::class)->messages);
        $this->assertEmpty(self::$container->get(MessageBHandler::class)->messages);

        self::$container->get(MessageBusInterface::class)->dispatch(new MessageA());
        self::$container->get(MessageBusInterface::class)->dispatch(new MessageB());
        self::$container->get(MessageBusInterface::class)->dispatch(new MessageA());

        $this->transport('async1')->queue()->assertCount(2);
        $this->transport('async2')->queue()->assertEmpty();
        $this->transport('async1')->queue()->assertContains(MessageA::class);
        $this->transport('async1')->queue()->assertContains(MessageA::class, 2);
        $this->transport('async2')->queue()->assertNotContains(MessageB::class);
        $this->assertEmpty(self::$container->get(MessageAHandler::class)->messages);
        $this->assertCount(1, self::$container->get(MessageBHandler::class)->messages);

        $this->transport('async1')->process(1);
        $this->transport('async2')->process(1);

        $this->transport('async1')->queue()->assertCount(1);
        $this->transport('async1')->queue()->assertContains(MessageA::class, 1);
        $this->transport('async2')->queue()->assertNotContains(MessageB::class);
        $this->assertCount(1, self::$container->get(MessageAHandler::class)->messages);
        $this->assertCount(1, self::$container->get(MessageBHandler::class)->messages);

        $this->transport('async1')->process();
        $this->transport('async2')->process();

        $this->transport('async1')->queue()->assertEmpty();
        $this->transport('async2')->queue()->assertEmpty();
        $this->assertCount(2, self::$container->get(MessageAHandler::class)->messages);
        $this->assertCount(1, self::$container->get(MessageBHandler::class)->messages);
    }

    /**
     * @test
     */
    public function can_enable_intercept(): void
    {
        self::bootKernel(['environment' => 'multi_transport']);

        $this->transport('async2')->intercept();

        $this->transport('async2')->queue()->assertEmpty();
        $this->assertEmpty(self::$container->get(MessageBHandler::class)->messages);

        self::$container->get(MessageBusInterface::class)->dispatch(new MessageB());
        self::$container->get(MessageBusInterface::class)->dispatch(new MessageB());

        $this->transport('async2')->queue()->assertCount(2);
    }

    /**
     * @test
     */
    public function cannot_access_queue_that_does_not_exist(): void
    {
        self::bootKernel(['environment' => 'multi_transport']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"invalid" not registered');

        $this->transport('invalid');
    }

    /**
     * @test
     */
    public function cannot_access_queue_that_is_not_test_transport(): void
    {
        self::bootKernel(['environment' => 'multi_transport']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Transport "async3" needs to be set to "test://" in your test config to use this feature.');

        $this->transport('async3');
    }

    /**
     * @test
     */
    public function queue_name_is_required_if_using_multiple_transports(): void
    {
        self::bootKernel(['environment' => 'multi_transport']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Multiple transports are registered (async1, async2, async3), you must specify a name.');

        $this->transport();
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

        $this->assertSame([$m1, $m2, $m3], $this->transport()->queue()->messages());
        $this->assertSame([$m1, $m3], $this->transport()->queue()->messages(MessageA::class));
        $this->assertSame([$m2], $this->transport()->queue()->messages(MessageB::class));
    }

    /**
     * @test
     */
    public function can_access_envelopes_on_queue(): void
    {
        self::bootKernel();

        self::$container->get(MessageBusInterface::class)->dispatch($m1 = new MessageA());
        self::$container->get(MessageBusInterface::class)->dispatch($m2 = new MessageB());
        self::$container->get(MessageBusInterface::class)->dispatch($m3 = new MessageA());

        $messages = \array_map(fn(Envelope $envelope) => $envelope->getMessage(), \iterator_to_array($this->transport()->queue()));

        $this->assertSame([$m1, $m2, $m3], $messages);
    }

    /**
     * @test
     */
    public function can_access_sent_acknowledged_and_rejected(): void
    {
        self::bootKernel();

        self::$container->get(MessageBusInterface::class)->dispatch($m1 = new MessageA(true));
        self::$container->get(MessageBusInterface::class)->dispatch($m2 = new MessageB());

        $this->assertCount(2, $this->transport()->queue());
        $this->assertCount(2, $this->transport()->sent());
        $this->assertCount(0, $this->transport()->acknowledged());
        $this->assertCount(0, $this->transport()->rejected());

        $this->transport()->process();

        $this->assertCount(0, $this->transport()->queue());
        $this->assertCount(2, $this->transport()->sent());
        $this->assertCount(1, $this->transport()->acknowledged());
        $this->assertCount(1, $this->transport()->rejected());
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
        $this->expectExceptionMessage('Cannot access transport - is ZenstruckMessengerTestBundle enabled in your test environment?');

        $this->transport();
    }

    /**
     * @test
     */
    public function can_configure_throwing_exceptions(): void
    {
        self::bootKernel();

        $this->transport()->throwExceptions();

        self::$container->get(MessageBusInterface::class)->dispatch(new MessageA(true));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('handling failed...');

        $this->transport()->process();
    }

    /**
     * @test
     */
    public function can_configure_throwing_exceptions_with_intercept_disabled(): void
    {
        self::bootKernel();

        $this->transport()->throwExceptions()->unblock();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('handling failed...');

        self::$container->get(MessageBusInterface::class)->dispatch(new MessageA(true));
    }

    /**
     * @test
     */
    public function can_disable_exception_catching_in_transport_config(): void
    {
        self::bootKernel(['environment' => 'multi_transport']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('handling failed...');

        self::$container->get(MessageBusInterface::class)->dispatch(new MessageB(true));
    }

    /**
     * @test
     */
    public function can_re_enable_exception_catching_if_disabled_in_transport_config(): void
    {
        self::bootKernel(['environment' => 'multi_transport']);

        $this->transport('async2')->catchExceptions();

        $this->transport('async2')->rejected()->assertEmpty();

        self::$container->get(MessageBusInterface::class)->dispatch(new MessageB(true));

        $this->transport('async2')->rejected()->assertCount(1);
    }

    protected static function bootKernel(array $options = []): KernelInterface
    {
        return parent::bootKernel(\array_merge(['environment' => 'single_transport'], $options));
    }
}
