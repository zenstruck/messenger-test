<?php

namespace Zenstruck\Messenger\Test\Tests\Fixture\Messenger;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class MessageG
{
    public \Closure $notSerializable;

    public function __construct()
    {
        $this->notSerializable = static fn() => true;
    }
}
