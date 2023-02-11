<?php

/*
 * This file is part of the zenstruck/messenger-test package.
 *
 * (c) Kevin Bond <kevinbond@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Zenstruck\Messenger\Test\Bus;

use Symfony\Component\Messenger\MessageBusInterface;

final class TestBusRegistry
{
    /** @var array<string, MessageBusInterface> */
    private array $buses = [];

    public function register(string $name, MessageBusInterface $bus): void
    {
        $this->buses[$name] = $bus;
    }

    public function get(?string $name = null): TestBus
    {
        if (0 === \count($this->buses)) {
            throw new \LogicException('No bus registered.');
        }

        if (null === $name && 1 !== \count($this->buses)) {
            throw new \InvalidArgumentException(\sprintf('Multiple buses are registered (%s), you must specify a name.', \implode(', ', \array_keys($this->buses))));
        }

        if (null === $name) {
            $name = \array_key_first($this->buses);
        }

        if (!$bus = $this->buses[$name] ?? null) {
            throw new \InvalidArgumentException("Bus \"{$name}\" not registered.");
        }

        if (!$bus instanceof TestBus) {
            throw new \LogicException("Bus \"{$name}\" needs to be a decorator of the bus.");
        }

        return $bus;
    }
}
