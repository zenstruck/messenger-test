<?php

namespace Zenstruck\Messenger\Test\Tests\Fixture\Messenger;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class MessageCHandler
{
    /** @var MessageC[] */
    public array $messages = [];

    public function __invoke(MessageC $message): void
    {
        $this->messages[] = $message;
    }
}
