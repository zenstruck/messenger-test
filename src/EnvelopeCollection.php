<?php

/*
 * This file is part of the zenstruck/messenger-test package.
 *
 * (c) Kevin Bond <kevinbond@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Zenstruck\Messenger\Test;

use Symfony\Component\Messenger\Envelope;
use Zenstruck\Assert;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 *
 * @implements \IteratorAggregate<TestEnvelope>
 */
abstract class EnvelopeCollection implements \IteratorAggregate, \Countable
{
    /** @var Envelope[] */
    private array $envelopes;

    /**
     * @internal
     */
    public function __construct(Envelope ...$envelopes)
    {
        $this->envelopes = $envelopes;
    }

    final public function assertEmpty(): static
    {
        return $this->assertCount(0);
    }

    final public function assertNotEmpty(): static
    {
        Assert::that($this)->isNotEmpty('Expected some messages but found none.');

        return $this;
    }

    final public function assertCount(int $count): static
    {
        Assert::that($this->envelopes)->hasCount($count, 'Expected {expected} messages but {actual} messages found.');

        return $this;
    }

    /**
     * @param class-string $messageClass
     */
    final public function assertContains(string $messageClass, ?int $times = null): static
    {
        $messages = $this->messages($messageClass);

        if (null !== $times) {
            Assert::that($messages)->hasCount(
                $times,
                'Expected to find "{message}" {expected} times but found {actual} times.',
                ['message' => $messageClass],
            );

            return $this;
        }

        Assert::that($messages)->isNotEmpty('Message "{message}" not found.', ['message' => $messageClass]);

        return $this;
    }

    /**
     * @param class-string $messageClass
     */
    final public function assertNotContains(string $messageClass): static
    {
        Assert::that($this->messages($messageClass))->isEmpty(
            'Found message "{message}" but should not.',
            ['message' => $messageClass],
        );

        return $this;
    }

    /**
     * @template T of object
     *
     * @param string|callable(T):mixed|null $filter only return the first envelope matching this message
     *                                              class-string or predicate
     */
    final public function first(callable|string|null $filter = null): TestEnvelope
    {
        $filter = new EnvelopeFilter($filter ?? static fn() => true);

        foreach ($this->envelopes as $envelope) {
            if ($filter($envelope)) {
                return new TestEnvelope($envelope);
            }
        }

        throw new \RuntimeException('No envelopes found.');
    }

    /**
     * The messages extracted from envelopes.
     *
     * @template T of object
     *
     * @param class-string<T>|null $class Only messages of this class
     *
     * @return ($class is string ? list<T> : array<object>)
     */
    final public function messages(?string $class = null): array
    {
        $messages = \array_map(static fn(Envelope $envelope) => $envelope->getMessage(), $this->envelopes);

        if (!$class) {
            return $messages;
        }

        return \array_values(\array_filter($messages, static fn(object $message) => $class === $message::class));
    }

    /**
     * @return TestEnvelope[]
     */
    final public function all(): array
    {
        return \iterator_to_array($this);
    }

    /**
     * @return \Traversable|TestEnvelope[]
     */
    final public function getIterator(): \Traversable
    {
        foreach ($this->envelopes as $envelope) {
            yield new TestEnvelope($envelope);
        }
    }

    final public function count(): int
    {
        return \count($this->envelopes);
    }
}
