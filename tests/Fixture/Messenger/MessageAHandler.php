<?php

namespace Zenstruck\Messenger\Test\Tests\Fixture\Messenger;

use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class MessageAHandler implements MessageHandlerInterface
{
    public $messages = [];

    public function __invoke(MessageA $message): void
    {
        if ($message->fail) {
            throw new \RuntimeException('handling failed...');
        }

        $this->messages[] = $message;
    }
}
