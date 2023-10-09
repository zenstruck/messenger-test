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

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Zenstruck\Messenger\Test\InteractsWithMessenger;
use Zenstruck\Messenger\Test\Tests\Fixture\Messenger\MessageA;
use Zenstruck\Messenger\Test\Tests\Fixture\Messenger\MessageB;
use Zenstruck\Messenger\Test\Tests\Fixture\Messenger\MessageC;

/**
 * @author Nicolas PHILIPPE <nikophil@gmail.com>
 */
final class DelayStampTestTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use InteractsWithMessenger;

    /**
     * @test
     * @group legacy
     */
    public function it_handles_messages_sequentially_without_delay_stamp_support(): void
    {
        self::bootKernel(['environment' => 'delay_stamp_disabled']);

        $transport = $this->transport('async');
        $transport->send(new Envelope(new MessageA(), [new DelayStamp(10_000)]));
        $transport->send(new Envelope(new MessageB()));
        $transport->send(new Envelope(new MessageC(), [new DelayStamp(5_000)]));

        $transport->acknowledged()->assertCount(0);

        $transport->process(1)->acknowledged()->assertCount(1)->assertContains(MessageA::class);
        $transport->process(1)->acknowledged()->assertCount(2)->assertContains(MessageB::class);
        $transport->process(1)->acknowledged()->assertCount(3)->assertContains(MessageC::class);
    }

    /**
     * @test
     */
    public function it_only_handles_message_without_delay_stamp_if_clock_not_mocked(): void
    {
        $transport = $this->transport('async');
        $transport->send(new Envelope(new MessageA(), [new DelayStamp(10_000)]));
        $transport->send(new Envelope(new MessageB()));
        $transport->send(new Envelope(new MessageC(), [new DelayStamp(5_000)]));

        $transport->acknowledged()->assertCount(0);

        $transport->process(1)->acknowledged()->assertCount(1)->assertContains(MessageB::class);
        $transport->process()->acknowledged()->assertCount(1);
    }

    /**
     * @test
     */
    public function it_handles_messages_depending_on_delay_stamp(): void
    {
        $clock = self::mockTime();

        $transport = $this->transport('async');
        $transport->send(new Envelope(new MessageA(), [new DelayStamp(10_000)]));
        $transport->send(new Envelope(new MessageB()));
        $transport->send(new Envelope(new MessageC(), [new DelayStamp(5_000)]));

        $transport->acknowledged()->assertCount(0);

        $transport->process()->acknowledged()->assertCount(1)->assertContains(MessageB::class);

        $clock->sleep(5);
        $transport->process()->acknowledged()->assertCount(2)->assertContains(MessageC::class);

        $clock->sleep(5);
        $transport->process()->acknowledged()->assertCount(3)->assertContains(MessageA::class);
    }

    protected static function bootKernel(array $options = []): KernelInterface // @phpstan-ignore-line
    {
        return parent::bootKernel(\array_merge(['environment' => 'single_transport'], $options));
    }
}
