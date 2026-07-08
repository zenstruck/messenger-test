<?php

/*
 * This file is part of the zenstruck/messenger-test package.
 *
 * (c) Kevin Bond <kevinbond@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Zenstruck\Messenger\Test\Retry;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Retry\RetryStrategyInterface;
use Zenstruck\Messenger\Test\Transport\TestTransport;

/**
 * @author Nicolas PHILIPPE <nikophil@gmail.com>
 *
 * @internal
 */
final class DisableableRetryStrategy implements RetryStrategyInterface
{
    public function __construct(
        private RetryStrategyInterface $inner,
        private string $transportName,
    ) {
    }

    public function isRetryable(Envelope $message, ?\Throwable $throwable = null): bool
    {
        if (TestTransport::isRetriesDisabledFor($this->transportName)) {
            return false;
        }

        return $this->inner->isRetryable($message, $throwable);
    }

    public function getWaitingTime(Envelope $message, ?\Throwable $throwable = null): int
    {
        return $this->inner->getWaitingTime($message, $throwable);
    }
}
