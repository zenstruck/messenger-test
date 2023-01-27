<?php

namespace Zenstruck\Messenger\Test\Bus;

use Symfony\Component\Messenger\Envelope;
use Zenstruck\Messenger\Test\EnvelopeCollection;

final class BusEnvelopeCollection extends EnvelopeCollection
{
    private TestBus $bus;

    public function __construct(TestBus $bus, Envelope ...$envelope)
    {
        $this->bus = $bus;
        parent::__construct(...$envelope);
    }

    public function back(): TestBus
    {
        return $this->bus;
    }
}
