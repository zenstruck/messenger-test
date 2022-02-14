<?php

namespace Zenstruck\Messenger\Test\Tests\Fixture\Messenger;

use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class MessageBHandler implements MessageHandlerInterface
{
    /** @var MessageB[] */
    public array $messages = [];

    public function __invoke(MessageB $message): void
    {
        if ($message->fail) {
            throw new \RuntimeException('handling failed...');
        }

        $this->messages[] = $message;
    }
}
