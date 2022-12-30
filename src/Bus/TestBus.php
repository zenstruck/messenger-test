<?php

namespace Zenstruck\Messenger\Test\Bus;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class TestBus implements MessageBusInterface
{
    private MessageBusInterface $decorated;
    /** @var object[] */
    private array $messages = [];

    public function __construct(MessageBusInterface $decorated)
    {
        $this->decorated = $decorated;
    }

    /** @return object[] */
    public function messages(): array
    {
        return $this->messages;
    }

    public function dispatch(object $message, array $stamps = []): Envelope
    {
        $this->messages[] = $envelope = $this->decorated->dispatch($message, $stamps);

        return $envelope;
    }
}
