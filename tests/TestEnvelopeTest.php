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

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Zenstruck\Messenger\Test\TestEnvelope;

final class TestEnvelopeTest extends TestCase
{
    #[Test]
    public function can_assert_stamp_presence_without_a_filter(): void
    {
        $envelope = new TestEnvelope(new Envelope(new \stdClass(), [new DelayStamp(1000)]));

        self::assertSame($envelope, $envelope->assertHasStamp(DelayStamp::class));
    }

    #[Test]
    #[TestWith([1000])]
    #[TestWith([2000])]
    public function matches_any_stamp_of_the_requested_type(int $delay): void
    {
        $envelope = new TestEnvelope(new Envelope(new \stdClass(), [
            new BusNameStamp('command'),
            new DelayStamp(1000),
            new DelayStamp(2000),
        ]));

        $result = $envelope->assertHasStamp(DelayStamp::class, static fn(DelayStamp $stamp) => $delay === $stamp->getDelay());

        self::assertSame($envelope, $result);
    }

    #[Test]
    public function fails_when_no_stamp_matches_the_filter(): void
    {
        $envelope = new TestEnvelope(new Envelope(new \stdClass(), [new DelayStamp(1000)]));
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('matching the filter but none was found');

        $envelope->assertHasStamp(DelayStamp::class, static fn(DelayStamp $stamp) => 2000 === $stamp->getDelay());
    }

    #[Test]
    public function fails_when_the_requested_stamp_is_missing(): void
    {
        $envelope = new TestEnvelope(new Envelope(new \stdClass()));
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Expected to find stamp');

        $envelope->assertHasStamp(DelayStamp::class, static fn(DelayStamp $stamp) => true);
    }
}
