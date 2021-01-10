<?php

namespace Zenstruck\Messenger\Test\Transport;

use PHPUnit\Framework\Assert as PHPUnit;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\Sync\SyncTransport;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Messenger\Worker;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class TestTransport implements TransportInterface, ResetInterface
{
    private MessageBusInterface $bus;
    private SyncTransport $syncTransport;
    private SerializerInterface $serializer;
    private bool $intercept;
    private array $sent = [];
    private array $acknowledged = [];
    private array $rejected = [];
    private array $queue = [];

    public function __construct(MessageBusInterface $bus, SerializerInterface $serializer, bool $intercept = true)
    {
        $this->bus = $bus;
        $this->syncTransport = new SyncTransport($bus);
        $this->serializer = $serializer;
        $this->intercept = $intercept;
    }

    /**
     * Processes any messages on the queue and processes future messages
     * immediately.
     */
    public function unblock(): self
    {
        // process any messages currently on queue
        $this->process();

        $this->intercept = false;

        return $this;
    }

    /**
     * Intercepts any future messages sent to queue.
     */
    public function intercept(): self
    {
        $this->intercept = true;

        return $this;
    }

    public function assertEmpty(): self
    {
        return $this->assertCount(0);
    }

    public function assertCount(int $count): self
    {
        PHPUnit::assertCount($count, $this->get(), \sprintf('Expected %d messages on queue, but %d messages found.', $count, \count($this->get())));

        return $this;
    }

    public function assertPushed(string $messageClass, ?int $times = null): self
    {
        $actual = $this->messages($messageClass);

        PHPUnit::assertNotEmpty($actual, "Message \"{$messageClass}\" not found on queue.");

        if (null !== $times) {
            PHPUnit::assertCount($times, $actual, \sprintf('Expected to find message "%s" on queue %d times but found %d times.', $messageClass, $times, \count($actual)));
        }

        return $this;
    }

    public function assertNotPushed(string $messageClass): self
    {
        $actual = $this->messages($messageClass);

        PHPUnit::assertEmpty($actual, "Message \"{$messageClass}\" is on queue but it should not be.");

        return $this;
    }

    /**
     * @param int|null $number int: the number of messages on the queue to process
     *                         null: process all messages on the queue
     */
    public function process(?int $number = null): self
    {
        if (null === $number && !$this->intercept) {
            return $this;
        }

        $count = \count($this->queue);

        if (null === $number) {
            return $this->process($count);
        }

        if (0 === $count) {
            return $this;
        }

        PHPUnit::assertGreaterThanOrEqual($number, \count($this->queue), "Tried to process {$number} queued messages but only {$count} are on in the queue.");

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnMessageLimitListener($number));

        $worker = new Worker([$this], $this->bus, $eventDispatcher);
        $worker->run(['sleep' => 0]);

        return $this;
    }

    /**
     * @return Envelope[]
     */
    public function sent(): array
    {
        return $this->decode($this->sent);
    }

    /**
     * @return Envelope[]
     */
    public function acknowledged(): array
    {
        return $this->decode($this->acknowledged);
    }

    /**
     * @return Envelope[]
     */
    public function rejected(): array
    {
        return $this->decode($this->rejected);
    }

    /**
     * The queued envelopes.
     *
     * @return Envelope[]
     */
    public function get(): array
    {
        if (!$this->intercept) {
            throw new InvalidArgumentException('You cannot access the queued messages when not in "intercept" mode.');
        }

        return \array_values($this->decode($this->queue));
    }

    /**
     * The queued messages (extracted from envelopes).
     *
     * @return object[]
     */
    public function messages(?string $class = null): array
    {
        $messages = \array_map(static fn(Envelope $envelope) => $envelope->getMessage(), $this->get());

        if (!$class) {
            return $messages;
        }

        return \array_filter($messages, static fn(object $message) => $class === \get_class($message));
    }

    /**
     * @internal
     */
    public function ack(Envelope $envelope): void
    {
        if (!$this->intercept) {
            throw new InvalidArgumentException('You cannot call ack() on the TestTransport when not in "intercept" mode.');
        }

        $this->acknowledged[] = $this->encode($envelope);
        $id = \spl_object_hash($envelope->getMessage());
        unset($this->queue[$id]);
    }

    /**
     * @internal
     */
    public function reject(Envelope $envelope): void
    {
        if (!$this->intercept) {
            throw new InvalidArgumentException('You cannot call reject() on the TestTransport not in "intercept" mode.');
        }

        $this->rejected[] = $this->encode($envelope);
        $id = \spl_object_hash($envelope->getMessage());
        unset($this->queue[$id]);
    }

    /**
     * @internal
     */
    public function send(Envelope $envelope): Envelope
    {
        if (!$this->intercept) {
            return $this->syncTransport->send($envelope);
        }

        $encodedEnvelope = $this->encode($envelope);
        $this->sent[] = $encodedEnvelope;
        $id = \spl_object_hash($envelope->getMessage());
        $this->queue[$id] = $encodedEnvelope;

        return $envelope;
    }

    /**
     * @internal
     */
    public function reset(): void
    {
        $this->sent = $this->queue = $this->rejected = $this->acknowledged = [];
    }

    private function encode(Envelope $envelope): array
    {
        return $this->serializer->encode($envelope);
    }

    /**
     * @return Envelope[]
     */
    private function decode(array $messagesEncoded): array
    {
        return \array_map([$this->serializer, 'decode'], $messagesEncoded);
    }
}
