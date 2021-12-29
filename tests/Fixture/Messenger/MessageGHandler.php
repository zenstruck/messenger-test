<?php

namespace Zenstruck\Messenger\Test\Tests\Fixture\Messenger;

use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class MessageGHandler implements MessageHandlerInterface
{
    public function __invoke(MessageG $message): void
    {
    }
}
