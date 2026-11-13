<?php

/*
 * This file is part of the zenstruck/messenger-test package.
 *
 * (c) Kevin Bond <kevinbond@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Zenstruck\Messenger\Test\Tests;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\OutboxStamp;
use Zenstruck\Assert;
use Zenstruck\Messenger\Test\InteractsWithMessenger;
use Zenstruck\Messenger\Test\Tests\Fixture\Messenger\MessageA;
use Zenstruck\Messenger\Test\Tests\Fixture\Messenger\MessageAHandler;
use Zenstruck\Messenger\Test\Tests\Fixture\Messenger\MessageB;
use Zenstruck\Messenger\Test\Tests\Fixture\Messenger\MessageC;
use Zenstruck\Messenger\Test\Tests\Fixture\Messenger\MessageCHandler;
use Zenstruck\Messenger\Test\Tests\Fixture\Messenger\MessageD;
use Zenstruck\Messenger\Test\Tests\Fixture\Messenger\WorkerMessageHandledListener;

final class ProcessAllTest extends KernelTestCase
{
    use ClockSensitiveTrait;
    use InteractsWithMessenger;

    #[Test]
    public function follows_messages_back_and_forth_between_transports(): void
    {
        $this->bus()->dispatch(new MessageD());

        self::assertSame(3, $this->processAll());
        $this->transport('first')->acknowledged()->assertCount(1);
        $this->transport('second')->acknowledged()->assertCount(2);
        $this->transport('first')->queue()->assertEmpty();
        $this->transport('second')->queue()->assertEmpty();
    }

    #[Test]
    public function only_processes_selected_transports(): void
    {
        $this->bus()->dispatch(new MessageA());
        $this->bus()->dispatch(new MessageD());

        self::assertSame(1, $this->processAll(['first']));
        $this->transport('second')->queue()->assertContains(MessageD::class);
    }

    #[Test]
    public function leaves_failure_transports_and_other_transport_types_alone(): void
    {
        $this->bus()->dispatch(new MessageA(true));
        $this->bus()->dispatch(new MessageB());
        $this->bus()->dispatch(new MessageC());

        self::assertSame(2, $this->processAll());
        $this->transport('failed')->queue()->assertContains(MessageA::class);
        $this->transport('failed')->acknowledged()->assertEmpty();
        $this->transport('second')->acknowledged()->assertContains(MessageB::class);
        self::assertSame([], self::getContainer()->get(MessageCHandler::class)->messages);
    }

    #[Test]
    public function can_explicitly_process_a_failure_transport(): void
    {
        $this->transport('failed')->send(new MessageA());

        self::assertSame(1, $this->processAll(['failed']));
        $this->transport('failed')->queue()->assertEmpty();
        $this->transport('failed')->acknowledged()->assertContains(MessageA::class);
    }

    #[Test]
    public function leaves_delayed_messages_until_they_are_due(): void
    {
        $clock = self::mockTime();
        $this->bus()->dispatch(new Envelope(new MessageA(), [new DelayStamp(1000)]));
        $this->bus()->dispatch(new MessageB());

        self::assertSame(1, $this->processAll());
        $this->transport('first')->queue()->assertContains(MessageA::class);
        $clock->sleep(1);

        self::assertSame(1, $this->processAll());
        $this->transport('first')->queue()->assertEmpty();
    }

    #[Test]
    public function validates_all_transport_names_before_processing(): void
    {
        $this->bus()->dispatch(new MessageA());

        Assert::that(fn() => $this->processAll(['first', 'missing']))->throws(\InvalidArgumentException::class);

        $this->transport('first')->queue()->assertContains(MessageA::class);
    }

    #[Test]
    public function rejects_explicitly_selected_non_test_transports(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('needs to be set to "test://"');

        $this->processAll(['ignored']);
    }

    #[Test]
    public function honors_each_transports_exception_setting_and_cleans_up_listeners(): void
    {
        $this->transport('second')->throwExceptions();
        $this->bus()->dispatch(new MessageB(true));

        Assert::that(fn() => $this->processAll())->throws(\RuntimeException::class);

        $this->transport('second')->catchExceptions();
        $this->bus()->dispatch(new MessageB(true));
        self::assertSame(1, $this->processAll());
        $this->transport('failed')->queue()->assertContains(MessageB::class);
    }

    #[Test]
    public function forwards_outbox_messages_then_handles_them(): void
    {
        if (!\class_exists(OutboxStamp::class)) {
            self::markTestSkipped('Symfony 8.2 is required.');
        }

        self::bootKernel(['environment' => 'outbox_processing']);
        $this->bus()->dispatch(new MessageA());

        self::assertSame(2, $this->processAll());
        $this->transport('second')->acknowledged()->assertCount(1);
        $this->transport('first')->acknowledged()->assertCount(1);
        self::assertCount(1, self::getContainer()->get(MessageAHandler::class)->messages);
    }

    #[Test]
    public function honors_transport_priorities(): void
    {
        if (!\class_exists(OutboxStamp::class)) {
            self::markTestSkipped('Symfony 8.2 is required.');
        }

        self::bootKernel(['environment' => 'priority_processing']);
        $this->bus()->dispatch(new MessageA());
        $this->bus()->dispatch(new MessageB());

        self::assertSame(2, $this->processAll(['first', 'second']));
        $messages = self::getContainer()->get(WorkerMessageHandledListener::class)->messages;
        self::assertInstanceOf(MessageB::class, $messages[0]);
        self::assertInstanceOf(MessageA::class, $messages[1]);
    }

    protected static function bootKernel(array $options = []): KernelInterface
    {
        return parent::bootKernel($options + ['environment' => 'multi_processing']);
    }
}
