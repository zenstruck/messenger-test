<?php

namespace Zenstruck\Messenger\Test\Tests\Fixture\Messenger;

use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class MessageFHandler implements MessageHandlerInterface
{
    public function __invoke(MessageF $message): void
    {
    }
}
