<?php

declare(strict_types=1);

namespace Zenstruck\Messenger\Test\Stamp;

use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;

final class AvailableAtStamp implements StampInterface
{
    public function __construct(private \DateTimeImmutable $availableAt)
    {
    }

    public static function fromDelayStamp(DelayStamp $delayStamp, \DateTimeImmutable $now): self
    {
        return new self(
            $now->modify(sprintf('+%d seconds', $delayStamp->getDelay() / 1000))
        );
    }

    public function getAvailableAt(): \DateTimeImmutable
    {
        return $this->availableAt;
    }
}
