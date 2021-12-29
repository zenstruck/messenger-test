<?php

namespace Zenstruck\Messenger\Test\Tests;

use PHPUnit\Framework\AssertionFailedError;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\SerializerStamp;
use Zenstruck\Assert;
use Zenstruck\Messenger\Test\InteractsWithMessenger;
use Zenstruck\Messenger\Test\TestEnvelope;
use Zenstruck\Messenger\Test\Tests\Fixture\Messenger\MessageA;
use Zenstruck\Messenger\Test\Tests\Fixture\Messenger\MessageAHandler;
use Zenstruck\Messenger\Test\Tests\Fixture\Messenger\MessageB;
use Zenstruck\Messenger\Test\Tests\Fixture\Messenger\MessageBHandler;
use Zenstruck\Messenger\Test\Tests\Fixture\Messenger\MessageC;
use Zenstruck\Messenger\Test\Tests\Fixture\Messenger\MessageD;
use Zenstruck\Messenger\Test\Tests\Fixture\Messenger\MessageE;
use Zenstruck\Messenger\Test\Tests\Fixture\Messenger\MessageF;
use Zenstruck\Messenger\Test\Tests\Fixture\Messenger\MessageG;
use Zenstruck\Messenger\Test\Tests\Fixture\NoBundleKernel;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class InteractsWithMessengerTest extends WebTestCase
{
    use InteractsWithMessenger;

    /**
     * @test
     */
    public function can_interact_with_queue(): void
    {
        self::bootKernel();

        $this->messenger()->queue()->assertEmpty();
        $this->assertEmpty(self::getContainer()->get(MessageAHandler::class)->messages);
        $this->assertEmpty(self::getContainer()->get(MessageBHandler::class)->messages);

        self::getContainer()->get(MessageBusInterface::class)->dispatch(new MessageA());
        self::getContainer()->get(MessageBusInterface::class)->dispatch(new MessageB());
        self::getContainer()->get(MessageBusInterface::class)->dispatch(new MessageA());

        $this->messenger()->queue()->assertCount(3);
        $this->messenger()->queue()->assertContains(MessageA::class);
        $this->messenger()->queue()->assertContains(MessageA::class, 2);
        $this->messenger()->queue()->assertContains(MessageB::class);
        $this->messenger()->queue()->assertContains(MessageB::class, 1);
        $this->messenger()->queue()->assertNotContains(MessageC::class);
        $this->messenger()->queue()->assertContains(MessageC::class, 0);
        $this->assertEmpty(self::getContainer()->get(MessageAHandler::class)->messages);
        $this->assertEmpty(self::getContainer()->get(MessageBHandler::class)->messages);

        $this->messenger()->process(2);

        $this->messenger()->queue()->assertCount(1);
        $this->messenger()->queue()->assertContains(MessageA::class, 1);
        $this->messenger()->queue()->assertNotContains(MessageB::class);
        $this->assertCount(1, self::getContainer()->get(MessageAHandler::class)->messages);
        $this->assertCount(1, self::getContainer()->get(MessageBHandler::class)->messages);

        $this->messenger()->process();

        $this->messenger()->queue()->assertEmpty();
        $this->messenger()->queue()->assertNotContains(MessageA::class);
        $this->messenger()->queue()->assertNotContains(MessageB::class);
        $this->assertCount(2, self::getContainer()->get(MessageAHandler::class)->messages);
        $this->assertCount(1, self::getContainer()->get(MessageBHandler::class)->messages);
    }

    /**
     * @test
     */
    public function can_use_envelope_collection_back(): void
    {
        self::bootKernel();

        $this->messenger()
            ->queue()->assertEmpty()->back()
            ->dispatched()->assertEmpty()->back()
            ->acknowledged()->assertEmpty()->back()
            ->rejected()->assertEmpty()
        ;

        self::getContainer()->get(MessageBusInterface::class)->dispatch(new MessageA());

        $this->messenger()
            ->queue()->assertCount(1)->back()
            ->dispatched()->assertCount(1)->back()
            ->acknowledged()->assertEmpty()->back()
            ->rejected()->assertEmpty()->back()
            ->process()
            ->queue()->assertEmpty()->back()
            ->dispatched()->assertCount(1)->back()
            ->acknowledged()->assertCount(1)->back()
            ->rejected()->assertEmpty()->back()
        ;
    }

    /**
     * @test
     */
    public function can_disable_intercept(): void
    {
        self::bootKernel();

        $this->messenger()->unblock();

        $this->messenger()->queue()->assertEmpty();
        $this->messenger()->acknowledged()->assertEmpty();
        $this->messenger()->dispatched()->assertEmpty();

        self::getContainer()->get(MessageBusInterface::class)->dispatch(new MessageA());
        self::getContainer()->get(MessageBusInterface::class)->dispatch(new MessageB());
        self::getContainer()->get(MessageBusInterface::class)->dispatch(new MessageA());

        $this->messenger()->queue()->assertEmpty();
        $this->messenger()->queue()->assertNotContains(MessageA::class);
        $this->messenger()->dispatched()->assertCount(3);
        $this->messenger()->dispatched()->assertContains(MessageA::class, 2);
        $this->messenger()->dispatched()->assertContains(MessageB::class, 1);
        $this->messenger()->acknowledged()->assertCount(3);
        $this->messenger()->acknowledged()->assertContains(MessageA::class, 2);
        $this->messenger()->acknowledged()->assertContains(MessageB::class, 1);
        $this->assertCount(2, self::getContainer()->get(MessageAHandler::class)->messages);
        $this->assertCount(1, self::getContainer()->get(MessageBHandler::class)->messages);
    }

    /**
     * @test
     */
    public function disabling_intercept_with_items_on_queue_processes_all(): void
    {
        self::bootKernel();

        self::getContainer()->get(MessageBusInterface::class)->dispatch(new MessageA());
        self::getContainer()->get(MessageBusInterface::class)->dispatch(new MessageB());
        self::getContainer()->get(MessageBusInterface::class)->dispatch(new MessageA());

        $this->messenger()->queue()->assertCount(3);

        $this->messenger()->process();

        $this->messenger()->queue()->assertEmpty();
        $this->assertCount(2, self::getContainer()->get(MessageAHandler::class)->messages);
        $this->assertCount(1, self::getContainer()->get(MessageBHandler::class)->messages);
    }

    /**
     * @test
     */
    public function unblocking_processes_existing_messages_on_queue(): void
    {
        self::bootKernel();

        self::getContainer()->get(MessageBusInterface::class)->dispatch(new MessageA());
        self::getContainer()->get(MessageBusInterface::class)->dispatch(new MessageB());

        $this->messenger()->queue()->assertCount(2);
        $this->messenger()->acknowledged()->assertEmpty();

        $this->messenger()->unblock();

        $this->messenger()->queue()->assertEmpty();
        $this->messenger()->acknowledged()->assertCount(2);
    }

    /**
     * @test
     */
    public function can_access_envelope_collection_items_via_first(): void
    {
        self::bootKernel();

        self::getContainer()->get(MessageBusInterface::class)->dispatch($m1 = new MessageA());
        self::getContainer()->get(MessageBusInterface::class)->dispatch($m2 = new MessageB());
        self::getContainer()->get(MessageBusInterface::class)->dispatch($m3 = new MessageA(true));

        $this->messenger()->queue()->assertCount(3);

        $this->assertSame($m1, $this->messenger()->queue()->first()->getMessage());
        $this->assertSame($m2, $this->messenger()->queue()->first(MessageB::class)->getMessage());
        $this->assertSame($m3, $this->messenger()->queue()->first(fn(Envelope $e) => $e->getMessage()->fail)->getMessage());
        $this->assertSame($m3, $this->messenger()->queue()->first(fn($e) => $e->getMessage()->fail)->getMessage());
        $this->assertSame($m3, $this->messenger()->queue()->first(fn(MessageA $m) => $m->fail)->getMessage());
    }

    /**
     * @test
     */
    public function envelope_collection_first_throws_exception_if_no_match(): void
    {
        self::bootKernel();

        $this->expectException(\RuntimeException::class);

        $this->messenger()->queue()->first();
    }

    /**
     * @test
     */
    public function can_make_stamp_assertions_on_test_envelope(): void
    {
        self::bootKernel();

        self::getContainer()->get(MessageBusInterface::class)->dispatch(new MessageA(), [new DelayStamp(1000)]);
        self::getContainer()->get(MessageBusInterface::class)->dispatch(new MessageB());

        $this->messenger()->queue()->first()->assertHasStamp(DelayStamp::class);
        $this->messenger()->queue()->first(MessageB::class)->assertNotHasStamp(DelayStamp::class);

        Assert::that(fn() => $this->messenger()->queue()->first()->assertHasStamp(SerializerStamp::class))
            ->throws(AssertionFailedError::class, \sprintf('Expected to find stamp "%s" but did not.', SerializerStamp::class))
        ;
    }

    /**
     * @test
     */
    public function cannot_access_queue_if_none_registered(): void
    {
        self::bootKernel(['environment' => 'test']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('No transports registered');

        $this->messenger();
    }

    /**
     * @test
     */
    public function accessing_transport_boots_kernel_if_not_yet_booted(): void
    {
        $this->messenger()->queue()->assertEmpty();
    }

    /**
     * @test
     */
    public function can_interact_with_multiple_queues(): void
    {
        self::bootKernel(['environment' => 'multi_transport']);

        $this->messenger('async1')->queue()->assertEmpty();
        $this->messenger('async2')->queue()->assertEmpty();
        $this->assertEmpty(self::getContainer()->get(MessageAHandler::class)->messages);
        $this->assertEmpty(self::getContainer()->get(MessageBHandler::class)->messages);

        self::getContainer()->get(MessageBusInterface::class)->dispatch(new MessageA());
        self::getContainer()->get(MessageBusInterface::class)->dispatch(new MessageB());
        self::getContainer()->get(MessageBusInterface::class)->dispatch(new MessageA());

        $this->messenger('async1')->queue()->assertCount(2);
        $this->messenger('async2')->queue()->assertEmpty();
        $this->messenger('async1')->queue()->assertContains(MessageA::class);
        $this->messenger('async1')->queue()->assertContains(MessageA::class, 2);
        $this->messenger('async2')->queue()->assertNotContains(MessageB::class);
        $this->assertEmpty(self::getContainer()->get(MessageAHandler::class)->messages);
        $this->assertCount(1, self::getContainer()->get(MessageBHandler::class)->messages);

        $this->messenger('async1')->process(1);

        $this->messenger('async1')->queue()->assertCount(1);
        $this->messenger('async1')->queue()->assertContains(MessageA::class, 1);
        $this->messenger('async2')->queue()->assertNotContains(MessageB::class);
        $this->assertCount(1, self::getContainer()->get(MessageAHandler::class)->messages);
        $this->assertCount(1, self::getContainer()->get(MessageBHandler::class)->messages);

        $this->messenger('async1')->process();

        $this->messenger('async1')->queue()->assertEmpty();
        $this->messenger('async2')->queue()->assertEmpty();
        $this->messenger('async2')->acknowledged()->assertCount(1);
        $this->messenger('async2')->acknowledged()->assertContains(MessageB::class, 1);
        $this->assertCount(2, self::getContainer()->get(MessageAHandler::class)->messages);
        $this->assertCount(1, self::getContainer()->get(MessageBHandler::class)->messages);
    }

    /**
     * @test
     */
    public function can_enable_intercept(): void
    {
        self::bootKernel(['environment' => 'multi_transport']);

        $this->messenger('async2')->intercept();

        $this->messenger('async2')->queue()->assertEmpty();
        $this->assertEmpty(self::getContainer()->get(MessageBHandler::class)->messages);

        self::getContainer()->get(MessageBusInterface::class)->dispatch(new MessageB());
        self::getContainer()->get(MessageBusInterface::class)->dispatch(new MessageB());

        $this->messenger('async2')->queue()->assertCount(2);
    }

    /**
     * @test
     */
    public function cannot_access_queue_that_does_not_exist(): void
    {
        self::bootKernel(['environment' => 'multi_transport']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"invalid" not registered');

        $this->messenger('invalid');
    }

    /**
     * @test
     */
    public function cannot_access_queue_that_is_not_test_transport(): void
    {
        self::bootKernel(['environment' => 'multi_transport']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Transport "async3" needs to be set to "test://" in your test config to use this feature.');

        $this->messenger('async3');
    }

    /**
     * @test
     */
    public function queue_name_is_required_if_using_multiple_transports(): void
    {
        self::bootKernel(['environment' => 'multi_transport']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Multiple transports are registered (async1, async2, async3), you must specify a name.');

        $this->messenger();
    }

    /**
     * @test
     */
    public function can_access_message_objects_on_queue(): void
    {
        self::bootKernel();

        self::getContainer()->get(MessageBusInterface::class)->dispatch($m1 = new MessageA());
        self::getContainer()->get(MessageBusInterface::class)->dispatch($m2 = new MessageB());
        self::getContainer()->get(MessageBusInterface::class)->dispatch($m3 = new MessageA());

        $this->assertSame([$m1, $m2, $m3], $this->messenger()->queue()->messages());
        $this->assertSame([$m1, $m3], $this->messenger()->queue()->messages(MessageA::class));
        $this->assertSame([$m2], $this->messenger()->queue()->messages(MessageB::class));
    }

    /**
     * @test
     */
    public function can_access_envelopes_on_envelope_collection(): void
    {
        self::bootKernel();

        self::getContainer()->get(MessageBusInterface::class)->dispatch($m1 = new MessageA());
        self::getContainer()->get(MessageBusInterface::class)->dispatch($m2 = new MessageB());
        self::getContainer()->get(MessageBusInterface::class)->dispatch($m3 = new MessageA());

        $messages = \array_map(fn(TestEnvelope $envelope) => $envelope->getMessage(), $this->messenger()->queue()->all());
        $messagesFromIterator = \array_map(fn(TestEnvelope $envelope) => $envelope->getMessage(), \iterator_to_array($this->messenger()->queue()));

        $this->assertSame([$m1, $m2, $m3], $messages);
        $this->assertSame([$m1, $m2, $m3], $messagesFromIterator);
    }

    /**
     * @test
     */
    public function can_access_sent_acknowledged_and_rejected(): void
    {
        self::bootKernel();

        self::getContainer()->get(MessageBusInterface::class)->dispatch($m1 = new MessageA(true));
        self::getContainer()->get(MessageBusInterface::class)->dispatch($m2 = new MessageB());

        $this->assertCount(2, $this->messenger()->queue());
        $this->assertCount(2, $this->messenger()->dispatched());
        $this->assertCount(0, $this->messenger()->acknowledged());
        $this->assertCount(0, $this->messenger()->rejected());

        $this->messenger()->process();

        $this->assertCount(0, $this->messenger()->queue());
        $this->assertCount(2, $this->messenger()->dispatched());
        $this->assertCount(1, $this->messenger()->acknowledged());
        $this->assertCount(1, $this->messenger()->rejected());
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

        $this->messenger();
    }

    /**
     * @test
     */
    public function can_configure_throwing_exceptions(): void
    {
        self::bootKernel();

        $this->messenger()->throwExceptions();

        self::getContainer()->get(MessageBusInterface::class)->dispatch(new MessageA(true));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('handling failed...');

        $this->messenger()->process();
    }

    /**
     * @test
     */
    public function can_configure_throwing_exceptions_with_intercept_disabled(): void
    {
        self::bootKernel();

        $this->messenger()->throwExceptions()->unblock();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('handling failed...');

        self::getContainer()->get(MessageBusInterface::class)->dispatch(new MessageA(true));
    }

    /**
     * @test
     */
    public function can_disable_exception_catching_in_transport_config(): void
    {
        self::bootKernel(['environment' => 'multi_transport']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('handling failed...');

        self::getContainer()->get(MessageBusInterface::class)->dispatch(new MessageB(true));
    }

    /**
     * @test
     */
    public function can_re_enable_exception_catching_if_disabled_in_transport_config(): void
    {
        self::bootKernel(['environment' => 'multi_transport']);

        $this->messenger('async2')->catchExceptions();

        $this->messenger('async2')->rejected()->assertEmpty();

        self::getContainer()->get(MessageBusInterface::class)->dispatch(new MessageB(true));

        $this->messenger('async2')->rejected()->assertCount(1);
    }

    /**
     * @test
     */
    public function transport_data_is_persisted_between_requests_and_kernel_shutdown(): void
    {
        self::bootKernel();

        self::getContainer()->get(MessageBusInterface::class)->dispatch(new MessageA());
        self::getContainer()->get(MessageBusInterface::class)->dispatch(new MessageA(true));

        $this->messenger()->queue()->assertCount(2);

        self::ensureKernelShutdown();
        self::bootKernel();

        $this->messenger()->queue()->assertCount(2);

        $this->messenger()->process();

        self::ensureKernelShutdown();
        self::bootKernel();

        $this->messenger()->queue()->assertEmpty();
        $this->messenger()->dispatched()->assertCount(2);
        $this->messenger()->acknowledged()->assertCount(1);
        $this->messenger()->rejected()->assertCount(1);

        self::ensureKernelShutdown();

        $client = self::createClient();

        $client->request('GET', '/dispatch');

        $this->messenger()->queue()->assertCount(1);
        $this->messenger()->dispatched()->assertCount(3);
        $this->messenger()->acknowledged()->assertCount(1);
        $this->messenger()->rejected()->assertCount(1);

        $client->request('GET', '/dispatch');

        $this->messenger()->queue()->assertCount(2);
        $this->messenger()->dispatched()->assertCount(4);
        $this->messenger()->acknowledged()->assertCount(1);
        $this->messenger()->rejected()->assertCount(1);
    }

    /**
     * @test
     */
    public function can_reset_transport_data(): void
    {
        self::bootKernel();

        self::getContainer()->get(MessageBusInterface::class)->dispatch(new MessageA());

        $this->messenger()->queue()->assertNotEmpty();

        $this->messenger()->reset();

        $this->messenger()->queue()->assertEmpty();
    }

    /**
     * @test
     */
    public function disabling_intercept_is_remembered_between_kernel_reboots(): void
    {
        self::bootKernel();

        $this->messenger()->unblock();

        self::getContainer()->get(MessageBusInterface::class)->dispatch(new MessageA());

        $this->messenger()->queue()->assertEmpty();
        $this->messenger()->dispatched()->assertCount(1);

        self::ensureKernelShutdown();
        self::bootKernel();

        self::getContainer()->get(MessageBusInterface::class)->dispatch(new MessageA());

        $this->messenger()->queue()->assertEmpty();
        $this->messenger()->dispatched()->assertCount(2);
    }

    /**
     * @test
     */
    public function throwing_exceptions_is_remembered_between_kernel_reboots(): void
    {
        self::bootKernel();

        $this->messenger()->throwExceptions();

        self::ensureKernelShutdown();
        self::bootKernel();

        self::getContainer()->get(MessageBusInterface::class)->dispatch(new MessageA(true));

        $this->expectException(\RuntimeException::class);
        $this->expectErrorMessage('handling failed...');

        $this->messenger()->process();
    }

    /**
     * @test
     */
    public function can_manually_send_message_to_transport_and_process(): void
    {
        self::bootKernel();

        $this->messenger()->queue()->assertEmpty();
        $this->assertEmpty(self::getContainer()->get(MessageAHandler::class)->messages);

        $this->messenger()->send(Envelope::wrap(new MessageA()));

        $this->messenger()->queue()->assertCount(1);
        $this->assertEmpty(self::getContainer()->get(MessageAHandler::class)->messages);

        $this->messenger()->process();

        $this->messenger()->queue()->assertEmpty();
        $this->assertCount(1, self::getContainer()->get(MessageAHandler::class)->messages);
    }

    /**
     * @test
     */
    public function process_all_is_recursive(): void
    {
        self::bootKernel();

        self::getContainer()->get(MessageBusInterface::class)->dispatch(new MessageD());

        $this->messenger()->queue()->assertCount(1);
        $this->messenger()->queue()->assertContains(MessageD::class, 1);

        $this->messenger()->process();

        $this->messenger()->queue()->assertEmpty();
        $this->messenger()->dispatched()->assertCount(3);
        $this->messenger()->acknowledged()->assertCount(3);
        $this->messenger()->acknowledged()->assertContains(MessageD::class, 1);
        $this->messenger()->acknowledged()->assertContains(MessageE::class, 1);
        $this->messenger()->acknowledged()->assertContains(MessageF::class, 1);
    }

    /**
     * @test
     */
    public function process_x_messages_is_recursive(): void
    {
        self::bootKernel();

        self::getContainer()->get(MessageBusInterface::class)->dispatch(new MessageD());

        $this->messenger()->queue()->assertCount(1);
        $this->messenger()->queue()->assertContains(MessageD::class, 1);

        $this->messenger()->process(1);

        $this->messenger()->queue()->assertCount(1);
        $this->messenger()->queue()->assertContains(MessageE::class, 1);
        $this->messenger()->acknowledged()->assertCount(1);
        $this->messenger()->acknowledged()->assertContains(MessageD::class, 1);

        $this->messenger()->process(2);

        $this->messenger()->queue()->assertEmpty();
        $this->messenger()->acknowledged()->assertCount(3);
        $this->messenger()->acknowledged()->assertContains(MessageE::class, 1);
        $this->messenger()->acknowledged()->assertContains(MessageF::class, 1);
    }

    /**
     * @test
     */
    public function fails_if_trying_to_process_more_messages_than_can_be_processed(): void
    {
        self::bootKernel();

        self::getContainer()->get(MessageBusInterface::class)->dispatch(new MessageA());

        Assert::that(fn() => $this->messenger()->process(2))->throws(function(AssertionFailedError $e) {
            $this->assertStringContainsString('Expected to process 2 messages but only processed 1.', $e->getMessage());
            $this->messenger()->queue()->assertEmpty();
            $this->messenger()->acknowledged()->assertContains(MessageA::class, 1);
        });
    }

    /**
     * @test
     */
    public function process_or_fail_processes_messages(): void
    {
        self::bootKernel();

        self::getContainer()->get(MessageBusInterface::class)->dispatch(new MessageA());

        $this->messenger()->queue()->assertCount(1);
        $this->messenger()->queue()->assertContains(MessageA::class, 1);

        $this->messenger()->processOrFail();

        $this->messenger()->queue()->assertEmpty();
        $this->messenger()->acknowledged()->assertCount(1);
        $this->messenger()->acknowledged()->assertContains(MessageA::class, 1);
    }

    /**
     * @test
     */
    public function process_or_fail_fails_if_no_messages_on_queue(): void
    {
        self::bootKernel();

        Assert::that(fn() => $this->messenger()->processOrFail())
            ->throws(AssertionFailedError::class, 'No messages to process.')
        ;
    }

    /**
     * @test
     */
    public function envelope_collection_assertions(): void
    {
        self::bootKernel();

        Assert::that(fn() => $this->messenger()->dispatched()->assertCount(2))
            ->throws(AssertionFailedError::class, 'Expected 2 messages but 0 messages found.')
        ;
        Assert::that(fn() => $this->messenger()->dispatched()->assertContains(MessageA::class))
            ->throws(AssertionFailedError::class, \sprintf('Message "%s" not found.', MessageA::class))
        ;
        Assert::that(fn() => $this->messenger()->dispatched()->assertNotEmpty())
            ->throws(AssertionFailedError::class, 'Expected some messages but found none.')
        ;

        self::getContainer()->get(MessageBusInterface::class)->dispatch(new MessageA());

        Assert::that(fn() => $this->messenger()->dispatched()->assertEmpty())
            ->throws(AssertionFailedError::class, 'Expected 0 messages but 1 messages found.')
        ;
        Assert::that(fn() => $this->messenger()->dispatched()->assertContains(MessageA::class, 2))
            ->throws(AssertionFailedError::class, \sprintf('Expected to find "%s" 2 times but found 1 times.', MessageA::class))
        ;
        Assert::that(fn() => $this->messenger()->dispatched()->assertNotContains(MessageA::class))
            ->throws(AssertionFailedError::class, \sprintf('Found message "%s" but should not.', MessageA::class))
        ;
    }

    /**
     * @test
     */
    public function messenger_worker_events_are_dispatched_when_processing(): void
    {
        $messages = [];

        self::bootKernel();

        self::getContainer()->get('event_dispatcher')->addListener(
            WorkerMessageHandledEvent::class,
            static function(WorkerMessageHandledEvent $event) use (&$messages) {
                $messages[] = $event->getEnvelope()->getMessage();
            }
        );

        self::getContainer()->get(MessageBusInterface::class)->dispatch($message = new MessageA());

        $this->messenger()->process();

        $this->assertCount(1, $messages);
        $this->assertSame($message, $messages[0]);
    }

    /**
     * @test
     */
    public function serialization_problem_assertions(): void
    {
        self::bootKernel(['environment' => 'multi_transport']);

        // "MessageG" is not serializable, but only transport async1 should catch this.
        Assert::that(fn() => $this->messenger('async1')->send(new Envelope(new MessageG())))
            ->throws(AssertionFailedError::class)
        ;

        Assert::run(fn() => $this->messenger('async2')->send(new Envelope(new MessageG())));
    }

    protected static function bootKernel(array $options = []): KernelInterface
    {
        return parent::bootKernel(\array_merge(['environment' => 'single_transport'], $options));
    }

    protected static function getContainer(): ContainerInterface
    {
        if (\method_exists(parent::class, 'getContainer')) {
            return parent::getContainer();
        }

        return self::$container;
    }
}
