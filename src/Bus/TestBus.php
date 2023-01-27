<?php

namespace Zenstruck\Messenger\Test\Bus;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class TestBus implements MessageBusInterface
{
    private MessageBusInterface $decorated;
    /** @var list<Envelope> */
    private static array $messages = [];

    // The setting applies to all buses
    private static bool $enableMessagesCollection = true;

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
        $envelope = $this->decorated->dispatch($message, $stamps);

        if (self::$enableMessagesCollection) {
            self::$messages[] = $envelope;
        }

        return $envelope;
    }

    /**
     * Resets data and options for all buses.
     */
    public static function resetAll(): void
    {
        self::$messages = [];
    }

    public static function enableMessagesCollection(): void
    {
        self::$enableMessagesCollection = true;
    }

    public static function disableMessagesCollection(): void
    {
        self::$enableMessagesCollection = false;
    }
}
