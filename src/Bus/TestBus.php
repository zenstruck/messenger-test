<?php

namespace Zenstruck\Messenger\Test\Bus;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class TestBus implements MessageBusInterface
{
    private MessageBusInterface $decorated;
    /** @var list<Envelope> */
    private array $messages = [];

    public function __construct(MessageBusInterface $decorated)
    {
        $this->decorated = $decorated;
    }

    /** @return list<Envelope> */
    public function dispatched(): array
    {
        return $this->messages;
    }

    public function dispatch(object $message, array $stamps = []): Envelope
    {
        $this->messages[] = $envelope = $this->decorated->dispatch($message, $stamps);

        return $envelope;
    }
}
