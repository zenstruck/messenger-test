<?php

namespace Zenstruck\Messenger\Test\Tests\Fixture\Messenger;

use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class MessageDHandler implements MessageHandlerInterface
{
    private MessageBusInterface $bus;

    public function __construct(MessageBusInterface $bus)
    {
        $this->bus = $bus;
    }

    public function __invoke(MessageD $message): void
    {
        $this->bus->dispatch(new MessageE());
    }
}
