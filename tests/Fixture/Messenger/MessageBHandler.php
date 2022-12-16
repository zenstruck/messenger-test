<?php

namespace Zenstruck\Messenger\Test\Tests\Fixture\Messenger;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class MessageBHandler
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
