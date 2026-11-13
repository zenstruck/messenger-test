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
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\CausationStamp;
use Symfony\Component\Messenger\Stamp\ChainStamp;
use Symfony\Component\Messenger\Stamp\CorrelationStamp;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\DispatchOnFailureStamp;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\FailedMessageStamp;
use Symfony\Component\Messenger\Stamp\MessageIdStamp;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Zenstruck\Messenger\Test\InteractsWithMessenger;
use Zenstruck\Messenger\Test\Tests\Fixture\Messenger\MessageA;
use Zenstruck\Messenger\Test\Tests\Fixture\Messenger\MessageB;
use Zenstruck\Messenger\Test\Tests\Fixture\Messenger\MessageC;
use Zenstruck\Messenger\Test\Tests\Fixture\Messenger\MessageF;
use Zenstruck\Messenger\Test\Tests\Fixture\Messenger\TenantStamp;
use Zenstruck\Messenger\Test\Tests\Fixture\Messenger\WorkerMessageHandledListener;

final class FlowsTest extends KernelTestCase
{
    use ClockSensitiveTrait;
    use InteractsWithMessenger;

    protected function setUp(): void
    {
        parent::setUp();

        if (!\class_exists(ChainStamp::class)) {
            self::markTestSkipped('Symfony 8.2 is required.');
        }

        self::bootKernel(['environment' => 'flows']);
    }

    #[Test]
    public function handles_a_chain_in_order_across_transports(): void
    {
        $this->bus()->dispatch(ChainStamp::envelope(new MessageA(), new MessageB(), new MessageF()));

        self::assertSame(3, $this->processAll());
        $messages = self::getContainer()->get(WorkerMessageHandledListener::class)->messages;
        self::assertSame([MessageA::class, MessageB::class, MessageF::class], \array_map(static fn(object $message) => $message::class, $messages));
        $this->transport('first')->queue()->assertEmpty();
        $this->transport('second')->queue()->assertEmpty();
    }

    #[Test]
    public function propagates_context_and_tracks_the_identity_of_each_step(): void
    {
        $root = $this->bus()->dispatch(ChainStamp::envelope(new MessageA(), new MessageB(), new MessageF()), [new TenantStamp('acme')]);
        $rootStamp = $root->last(MessageIdStamp::class);
        $rootId = $rootStamp->getId();

        $this->processAll();

        $second = $this->transport('second')->acknowledged()->first(MessageB::class);
        $third = $this->transport('second')->acknowledged()->first(MessageF::class);
        $secondStamp = $second->last(MessageIdStamp::class);
        $secondId = $secondStamp->getId();
        $thirdStamp = $third->last(MessageIdStamp::class);
        self::assertNotSame($rootId, $secondId);
        self::assertNotSame($secondId, $thirdStamp->getId());
        $second->assertHasStamp(CausationStamp::class, static fn(CausationStamp $stamp) => $stamp->getId() === $rootId);
        $third->assertHasStamp(CausationStamp::class, static fn(CausationStamp $stamp) => $stamp->getId() === $secondId);
        $second->assertHasStamp(CorrelationStamp::class, static fn(CorrelationStamp $stamp) => $stamp->getId() === $rootId);
        $third->assertHasStamp(CorrelationStamp::class, static fn(CorrelationStamp $stamp) => $stamp->getId() === $rootId);
        $second->assertHasStamp(TenantStamp::class, static fn(TenantStamp $stamp) => $stamp->isFor('acme'));
        $third->assertHasStamp(TenantStamp::class, static fn(TenantStamp $stamp) => $stamp->isFor('acme'));
    }

    #[Test]
    public function leaves_delayed_steps_queued_until_they_are_due(): void
    {
        $clock = self::mockTime();
        $this->bus()->dispatch(ChainStamp::envelope(new MessageA(), new Envelope(new MessageB(), [new DelayStamp(1000)]), new MessageF()));

        self::assertSame(1, $this->processAll());
        $this->transport('second')->queue()->assertContains(MessageB::class);
        $this->bus()->dispatched()->assertNotContains(MessageF::class);
        $clock->sleep(1);

        self::assertSame(2, $this->processAll());
        $this->transport('second')->acknowledged()->assertContains(MessageF::class);
    }

    #[Test]
    public function a_failed_step_stops_the_chain(): void
    {
        $this->bus()->dispatch(ChainStamp::envelope(new MessageA(), new MessageB(true), new MessageF()));

        self::assertSame(2, $this->processAll());
        $this->transport('failed')->queue()->assertContains(MessageB::class);
        $this->bus()->dispatched()->assertNotContains(MessageF::class);
    }

    #[Test]
    public function compensates_a_failed_step_once_and_preserves_its_context(): void
    {
        $chain = ChainStamp::envelope(new MessageA(), new MessageB(true), new MessageF());
        $this->bus()->dispatch($chain, [new DispatchOnFailureStamp(new MessageC()), new TenantStamp('acme')]);

        self::assertSame(3, $this->processAll());
        $this->bus()->dispatched()->assertContains(MessageC::class, 1);
        $this->bus()->dispatched()->assertNotContains(MessageF::class);
        $this->transport('failed')->queue()->assertEmpty();
        $compensation = $this->transport('first')->acknowledged()->first(MessageC::class);
        $compensation->assertHasStamp(FailedMessageStamp::class, static fn(FailedMessageStamp $stamp) => $stamp->getMessage() instanceof MessageB);
        $compensation->assertHasStamp(ErrorDetailsStamp::class, static fn(ErrorDetailsStamp $stamp) => 'handling failed...' === $stamp->getExceptionMessage());
        $compensation->assertHasStamp(TenantStamp::class, static fn(TenantStamp $stamp) => $stamp->isFor('acme'));
        $failed = $this->transport('second')->rejected()->first(MessageB::class);
        $failedStamp = $failed->last(MessageIdStamp::class);
        $failedId = $failedStamp->getId();
        $compensation->assertHasStamp(CausationStamp::class, static fn(CausationStamp $stamp) => $stamp->getId() === $failedId);
    }

    #[Test]
    public function compensates_only_after_retries_are_exhausted(): void
    {
        $clock = self::mockTime();
        $this->transport('second')->enableRetries();
        $chain = ChainStamp::envelope(new MessageA(), new MessageB(true), new MessageF());
        $this->bus()->dispatch($chain, [new DispatchOnFailureStamp(new MessageC())]);

        self::assertSame(2, $this->processAll());
        $this->bus()->dispatched()->assertNotContains(MessageC::class);
        $this->transport('second')->queue()->assertContains(MessageB::class);
        $clock->sleep(1);

        self::assertSame(2, $this->processAll());
        $this->bus()->dispatched()->assertContains(MessageC::class, 1);
        $this->bus()->dispatched()->assertNotContains(MessageF::class);
        $this->transport('first')->acknowledged()->assertContains(MessageC::class);
        $this->transport('failed')->queue()->assertEmpty();
    }

    #[Test]
    public function falls_back_to_the_failure_transport_when_compensation_cannot_be_dispatched(): void
    {
        $chain = ChainStamp::envelope(new MessageA(), new MessageB(true), new MessageF());
        $compensation = new Envelope(new MessageC(), [new TransportNamesStamp('missing')]);
        $this->bus()->dispatch($chain, [new DispatchOnFailureStamp($compensation)]);

        self::assertSame(2, $this->processAll());
        $this->transport('failed')->queue()->assertContains(MessageB::class);
        $failed = $this->transport('failed')->queue()->first(MessageB::class);
        $failed->assertHasStamp(ErrorDetailsStamp::class, static fn(ErrorDetailsStamp $stamp) => 'handling failed...' === $stamp->getExceptionMessage());
        $this->bus()->dispatched()->assertNotContains(MessageC::class);
        $this->bus()->dispatched()->assertNotContains(MessageF::class);
    }
}
