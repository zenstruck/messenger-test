<?php

namespace Zenstruck\Messenger\Test\Transport;

use PHPUnit\Framework\Assert as PHPUnit;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Messenger\Worker;
use Zenstruck\Messenger\Test\EnvelopeCollection;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class TestTransport implements TransportInterface
{
    private const DEFAULT_OPTIONS = [
        'intercept' => true,
        'catch_exceptions' => true,
    ];

    private string $name;
    private MessageBusInterface $bus;
    private SerializerInterface $serializer;

    /** @var array<string, bool> */
    private static array $intercept = [];

    /** @var array<string, bool> */
    private static array $catchExceptions = [];

    /** @var array<string, Envelope[]> */
    private static array $sent = [];

    /** @var array<string, Envelope[]> */
    private static array $acknowledged = [];

    /** @var array<string, Envelope[]> */
    private static array $rejected = [];

    /** @var array<string, Envelope[]> */
    private static array $queue = [];

    /**
     * @internal
     */
    public function __construct(string $name, MessageBusInterface $bus, SerializerInterface $serializer, array $options = [])
    {
        $options = \array_merge(self::DEFAULT_OPTIONS, $options);

        $this->name = $name;
        $this->bus = $bus;
        $this->serializer = $serializer;

        self::$intercept[$name] ??= $options['intercept'];
        self::$catchExceptions[$name] ??= $options['catch_exceptions'];
    }

    /**
     * Processes any messages on the queue and processes future messages
     * immediately.
     */
    public function unblock(): self
    {
        // process any messages currently on queue
        $this->process();

        self::$intercept[$this->name] = false;

        return $this;
    }

    /**
     * Intercepts any future messages sent to queue.
     */
    public function intercept(): self
    {
        self::$intercept[$this->name] = true;

        return $this;
    }

    public function catchExceptions(): self
    {
        self::$catchExceptions[$this->name] = true;

        return $this;
    }

    public function throwExceptions(): self
    {
        self::$catchExceptions[$this->name] = false;

        return $this;
    }

    /**
     * @param int|null $number int: the number of messages on the queue to process
     *                         null: process all messages on the queue
     */
    public function process(?int $number = null): self
    {
        $count = \count(self::$queue[$this->name] ?? []);

        if (null === $number) {
            return $this->process($count);
        }

        if (0 === $count) {
            return $this;
        }

        PHPUnit::assertGreaterThanOrEqual($number, $count, "Tried to process {$number} queued messages but only {$count} are on in the queue.");

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnMessageLimitListener($number));

        if (!$this->isCatchingExceptions()) {
            $eventDispatcher->addListener(WorkerMessageFailedEvent::class, function(WorkerMessageFailedEvent $event) {
                throw $event->getThrowable();
            });
        }

        $worker = new Worker([$this], $this->bus, $eventDispatcher);
        $worker->run(['sleep' => 0]);

        return $this;
    }

    public function queue(): EnvelopeCollection
    {
        return new EnvelopeCollection(...\array_values(self::$queue[$this->name] ?? []));
    }

    public function sent(): EnvelopeCollection
    {
        return new EnvelopeCollection(...self::$sent[$this->name] ?? []);
    }

    public function acknowledged(): EnvelopeCollection
    {
        return new EnvelopeCollection(...self::$acknowledged[$this->name] ?? []);
    }

    public function rejected(): EnvelopeCollection
    {
        return new EnvelopeCollection(...self::$rejected[$this->name] ?? []);
    }

    /**
     * @internal
     */
    public function get(): iterable
    {
        return \array_values(self::$queue[$this->name] ?? []);
    }

    /**
     * @internal
     */
    public function ack(Envelope $envelope): void
    {
        self::$acknowledged[$this->name][] = $envelope;
        unset(self::$queue[$this->name][\spl_object_hash($envelope->getMessage())]);
    }

    /**
     * @internal
     */
    public function reject(Envelope $envelope): void
    {
        self::$rejected[$this->name][] = $envelope;
        unset(self::$queue[$this->name][\spl_object_hash($envelope->getMessage())]);
    }

    public function send(Envelope $envelope): Envelope
    {
        // ensure serialization works (todo configurable? better error on failure?)
        $this->serializer->decode($this->serializer->encode($envelope));

        self::$sent[$this->name][] = $envelope;
        self::$queue[$this->name][\spl_object_hash($envelope->getMessage())] = $envelope;

        if (!$this->isIntercepting()) {
            $this->process();
        }

        return $envelope;
    }

    /**
     * Resets all the data for this transport.
     */
    public function reset(): void
    {
        self::$queue[$this->name] = self::$sent[$this->name] = self::$acknowledged[$this->name] = self::$rejected[$this->name] = [];
    }

    /**
     * Resets data and options for all transports.
     */
    public static function resetAll(): void
    {
        self::$queue = self::$sent = self::$acknowledged = self::$rejected = self::$intercept = self::$catchExceptions = [];
    }

    private function isIntercepting(): bool
    {
        return self::$intercept[$this->name];
    }

    private function isCatchingExceptions(): bool
    {
        return self::$catchExceptions[$this->name];
    }
}
