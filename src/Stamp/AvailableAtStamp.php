<?php

/*
 * This file is part of the zenstruck/messenger-test package.
 *
 * (c) Kevin Bond <kevinbond@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Zenstruck\Messenger\Test\Stamp;

use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * @internal
 */
final class AvailableAtStamp implements StampInterface
{
    public function __construct(private \DateTimeImmutable $availableAt)
    {
    }

    public static function fromDelayStamp(DelayStamp $delayStamp, \DateTimeImmutable $now): self
    {
        return new self(
            $now->modify(\sprintf('+%d seconds', $delayStamp->getDelay() / 1000))
        );
    }

    public function getAvailableAt(): \DateTimeImmutable
    {
        return $this->availableAt;
    }
}
