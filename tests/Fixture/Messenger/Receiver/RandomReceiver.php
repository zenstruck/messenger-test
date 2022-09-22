<?php

namespace Zenstruck\Messenger\Test\Tests\Fixture\Messenger\Receiver;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;

class RandomReceiver implements ReceiverInterface
{
    /**
     * @return Envelope[]
     *
     * @throws TransportException
     */
    public function get(): iterable
    {
        yield;
    }

    /**
     * @throws TransportException
     */
    public function ack(Envelope $envelope): void
    {
    }

    /**
     * @throws TransportException
     */
    public function reject(Envelope $envelope): void
    {
    }
}
