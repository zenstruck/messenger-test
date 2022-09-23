<?php

namespace Zenstruck\Messenger\Test\Tests\Fixture\Messenger\Receiver;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;

class RandomReceiver implements ReceiverInterface
{
    public function get(): iterable
    {
        yield;
    }

    public function ack(Envelope $envelope): void
    {
    }

    public function reject(Envelope $envelope): void
    {
    }
}
