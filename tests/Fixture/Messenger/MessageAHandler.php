<?php

namespace Zenstruck\Messenger\Test\Tests\Fixture\Messenger;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class MessageAHandler
{
    /** @var MessageA[] */
    public array $messages = [];

    public function __invoke(MessageA $message): void
    {
        if ($message->fail) {
            throw new \RuntimeException('handling failed...');
        }

        $this->messages[] = $message;
    }
}
