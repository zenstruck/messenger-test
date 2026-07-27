<?php

/*
 * This file is part of the zenstruck/messenger-test package.
 *
 * (c) Kevin Bond <kevinbond@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Zenstruck\Messenger\Test\Transport;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Zenstruck\Messenger\Test\EnvelopeFilter;

/**
 * @internal
 */
final class FilteredReceiver implements ReceiverInterface
{
    public function __construct(
        private readonly TestTransport $decorated,
        private readonly EnvelopeFilter $filter,
    ) {
    }

    public function get(int $fetchSize = 1): iterable
    {
        if (1 !== $fetchSize) {
            throw new \InvalidArgumentException(\sprintf('"%s()" only supports fetchSize of 1, "%s" given.', __METHOD__, $fetchSize));
        }

        return $this->decorated->getMatching($this->filter);
    }

    public function ack(Envelope $envelope): void
    {
        $this->decorated->ack($envelope);
    }

    public function reject(Envelope $envelope): void
    {
        $this->decorated->reject($envelope);
    }
}
