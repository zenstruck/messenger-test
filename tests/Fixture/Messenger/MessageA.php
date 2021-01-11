<?php

namespace Zenstruck\Messenger\Test\Tests\Fixture\Messenger;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class MessageA
{
    public bool $fail;

    public function __construct(bool $fail = false)
    {
        $this->fail = $fail;
    }
}
