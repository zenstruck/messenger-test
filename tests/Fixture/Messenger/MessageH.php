<?php

declare(strict_types=1);

/*
 * This file is part of the zenstruck/messenger-test package.
 *
 * (c) Kevin Bond <kevinbond@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Zenstruck\Messenger\Test\Tests\Fixture\Messenger;

class MessageH
{
    public function __construct(private readonly string $name)
    {
    }

    public function __serialize(): array
    {
        return [$this->name];
    }

    public function __unserialize(array $data): void
    {
        // make unserialization fail
    }

    public function getName(): string
    {
        return $this->name;
    }
}
