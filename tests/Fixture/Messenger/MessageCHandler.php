<?php

namespace Zenstruck\Messenger\Test\Tests\Fixture\Messenger;

use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class MessageCHandler implements MessageHandlerInterface
{
    /** @var MessageC[] */
    public array $messages = [];

    public function __invoke(MessageC $message): void
    {
        $this->messages[] = $message;
    }
}
