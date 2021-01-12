<?php

namespace Zenstruck\Messenger\Test;

use PHPUnit\Framework\Assert as PHPUnit;
use Symfony\Component\Messenger\Envelope;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class EnvelopeCollection implements \IteratorAggregate, \Countable
{
    private array $envelopes;

    /**
     * @internal
     */
    public function __construct(Envelope ...$envelopes)
    {
        $this->envelopes = $envelopes;
    }

    public function assertEmpty(): self
    {
        return $this->assertCount(0);
    }

    public function assertNotEmpty(): self
    {
        PHPUnit::assertNotEmpty($this, 'Expected some messages but found none.');

        return $this;
    }

    public function assertCount(int $count): self
    {
        PHPUnit::assertCount($count, $this->envelopes, \sprintf('Expected %d messages, but %d messages found.', $count, \count($this->envelopes)));

        return $this;
    }

    public function assertContains(string $messageClass, ?int $times = null): self
    {
        $actual = $this->messages($messageClass);

        PHPUnit::assertNotEmpty($actual, "Message \"{$messageClass}\" not found.");

        if (null !== $times) {
            PHPUnit::assertCount($times, $actual, \sprintf('Expected to find message "%s" %d times but found %d times.', $messageClass, $times, \count($actual)));
        }

        return $this;
    }

    public function assertNotContains(string $messageClass): self
    {
        $actual = $this->messages($messageClass);

        PHPUnit::assertEmpty($actual, "Found message \"{$messageClass}\" but should not.");

        return $this;
    }

    /**
     * The messages extracted from envelopes.
     *
     * @param string|null $class Only messages of this class
     *
     * @return object[]
     */
    public function messages(?string $class = null): array
    {
        $messages = \array_map(static fn(Envelope $envelope) => $envelope->getMessage(), $this->envelopes);

        if (!$class) {
            return $messages;
        }

        return \array_values(\array_filter($messages, static fn(object $message) => $class === \get_class($message)));
    }

    public function getIterator(): iterable
    {
        return new \ArrayIterator($this->envelopes);
    }

    public function count(): int
    {
        return \count($this->envelopes);
    }
}
