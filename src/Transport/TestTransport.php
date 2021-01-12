<?php

namespace Zenstruck\Messenger\Test\Transport;

use PHPUnit\Framework\Assert as PHPUnit;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\Sync\SyncTransport;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Messenger\Worker;
use Symfony\Contracts\Service\ResetInterface;
use Zenstruck\Messenger\Test\EnvelopeCollection;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class TestTransport implements TransportInterface, ResetInterface
{
    private MessageBusInterface $bus;
    private SyncTransport $syncTransport;
    private SerializerInterface $serializer;
    private bool $intercept;

    /** @var Envelope[] */
    private array $sent = [];

    /** @var Envelope[] */
    private array $acknowledged = [];

    /** @var Envelope[] */
    private array $rejected = [];

    /** @var Envelope[] */
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

    /**
     * @param int|null $number int: the number of messages on the queue to process
     *                         null: process all messages on the queue
     */
    public function process(?int $number = null): self
    {
        if (!$this->intercept) {
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

    public function queue(): EnvelopeCollection
    {
        return new EnvelopeCollection(...\array_values($this->queue));
    }

    public function sent(): EnvelopeCollection
    {
        return new EnvelopeCollection(...$this->sent);
    }

    public function acknowledged(): EnvelopeCollection
    {
        return new EnvelopeCollection(...$this->acknowledged);
    }

    public function rejected(): EnvelopeCollection
    {
        return new EnvelopeCollection(...$this->rejected);
    }

    /**
     * @internal
     */
    public function get(): iterable
    {
        return \array_values($this->queue);
    }

    /**
     * @internal
     */
    public function ack(Envelope $envelope): void
    {
        $this->acknowledged[] = $envelope;
        unset($this->queue[\spl_object_hash($envelope->getMessage())]);
    }

    /**
     * @internal
     */
    public function reject(Envelope $envelope): void
    {
        $this->rejected[] = $envelope;
        unset($this->queue[\spl_object_hash($envelope->getMessage())]);
    }

    /**
     * @internal
     */
    public function send(Envelope $envelope): Envelope
    {
        if (!$this->intercept) {
            return $this->syncTransport->send($envelope);
        }

        // ensure serialization works (todo configurable? better error on failure?)
        $this->serializer->decode($this->serializer->encode($envelope));

        $this->sent[] = $envelope;
        $this->queue[\spl_object_hash($envelope->getMessage())] = $envelope;

        return $envelope;
    }

    /**
     * @internal
     */
    public function reset(): void
    {
        $this->sent = $this->queue = $this->rejected = $this->acknowledged = [];
    }
}
