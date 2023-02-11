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
use Zenstruck\Messenger\Test\EnvelopeCollection;

final class TransportEnvelopeCollection extends EnvelopeCollection
{
    private TestTransport $transport;

    public function __construct(TestTransport $transport, Envelope ...$envelopes)
    {
        $this->transport = $transport;
        parent::__construct(...$envelopes);
    }

    public function back(): TestTransport
    {
        return $this->transport;
    }
}
