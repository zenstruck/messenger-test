<?php

namespace Zenstruck\Messenger\Test\Bus;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class TestBus implements MessageBusInterface
{
    private MessageBusInterface $decorated;
    /** @var list<Envelope> */
    private static array $messages = [];

    public function __construct(MessageBusInterface $decorated)
    {
        $this->decorated = $decorated;
    }

    /** @return list<Envelope> */
    public function dispatched(): array
    {
        return self::$messages;
    }

    public function dispatch(object $message, array $stamps = []): Envelope
    {
        self::$messages[] = $envelope = $this->decorated->dispatch($message, $stamps);

        return $envelope;
    }

    /**
     * Resets data and options for all buses.
     */
    public static function resetAll(): void
    {
        self::$messages = [];
    }
}
