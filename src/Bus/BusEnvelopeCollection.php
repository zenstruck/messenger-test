<?php

/*
 * This file is part of the zenstruck/messenger-test package.
 *
 * (c) Kevin Bond <kevinbond@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Zenstruck\Messenger\Test\Bus;

use Symfony\Component\Messenger\Envelope;
use Zenstruck\Messenger\Test\EnvelopeCollection;

final class BusEnvelopeCollection extends EnvelopeCollection
{
    public function __construct(private TestBus $bus, Envelope ...$envelope)
    {
        parent::__construct(...$envelope);
    }

    public function back(): TestBus
    {
        return $this->bus;
    }
}
