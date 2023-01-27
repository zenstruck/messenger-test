<?php

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
