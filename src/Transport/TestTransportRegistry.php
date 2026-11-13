<?php

/*
 * This file is part of the zenstruck/messenger-test package.
 *
 * (c) Kevin Bond <kevinbond@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Zenstruck\Messenger\Test\Transport;

use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 *
 * @internal
 */
final class TestTransportRegistry
{
    /** @var TransportInterface[] */
    private array $transports = [];

    /** @var array<string, array{failure: bool, priority: int}> */
    private array $options = [];

    public function register(string $name, TransportInterface $transport, bool $failureTransport = false, int $priority = 0): void
    {
        $this->transports[$name] = $transport;
        $this->options[$name] = ['failure' => $failureTransport, 'priority' => $priority];
    }

    /**
     * @param list<string> $names
     */
    public function processAll(array $names = [], int $maxMessages = 1000): int
    {
        $transports = [];

        foreach ($names ?: \array_keys($this->transports) as $name) {
            if (!$names && ($this->options[$name]['failure'] || !$this->transports[$name] instanceof TestTransport)) {
                continue;
            }

            $transports[$name] = $this->get($name);
        }

        \uksort($transports, fn(string $first, string $second) => $this->options[$second]['priority'] <=> $this->options[$first]['priority']);

        return TestTransport::processAll($transports, $maxMessages);
    }

    public function get(?string $name = null): TestTransport
    {
        if (0 === \count($this->transports)) {
            throw new \LogicException('No transports registered.');
        }

        if (null === $name && 1 !== \count($this->transports)) {
            throw new \InvalidArgumentException(\sprintf('Multiple transports are registered (%s), you must specify a name.', \implode(', ', \array_keys($this->transports))));
        }

        if (null === $name) {
            $name = \array_key_first($this->transports);
        }

        if (!$transport = $this->transports[$name] ?? null) {
            throw new \InvalidArgumentException("Transport \"{$name}\" not registered.");
        }

        if (!$transport instanceof TestTransport) {
            throw new \LogicException("Transport \"{$name}\" needs to be set to \"test://\" in your test config to use this feature.");
        }

        return $transport;
    }
}
