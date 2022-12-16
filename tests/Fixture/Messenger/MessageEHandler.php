<?php

namespace Zenstruck\Messenger\Test\Tests\Fixture\Messenger;

use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class MessageEHandler
{
    private MessageBusInterface $bus;

    public function __construct(MessageBusInterface $bus)
    {
        $this->bus = $bus;
    }

    public function __invoke(MessageE $message): void
    {
        $this->bus->dispatch(new MessageF());
    }
}
