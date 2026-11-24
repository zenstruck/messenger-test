<?php

/*
 * This file is part of the zenstruck/messenger-test package.
 *
 * (c) Kevin Bond <kevinbond@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Zenstruck\Messenger\Test\Tests\Fixture\Messenger;

/**
 * Can be encoded, but fails to be decoded.
 */
final class MessageI
{
    public function __serialize(): array
    {
        return [];
    }

    public function __unserialize(array $data): void
    {
        throw new \RuntimeException('MessageI cannot be decoded.');
    }
}
