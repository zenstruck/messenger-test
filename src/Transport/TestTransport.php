<?php

namespace Zenstruck\Messenger\Test\Transport;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Messenger\Worker;
use Zenstruck\Assert;
use Zenstruck\Messenger\Test\EnvelopeCollection;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class TestTransport implements TransportInterface
{
    private const DEFAULT_OPTIONS = [
        'intercept' => true,
        'catch_exceptions' => true,
        'test_serialization' => true,
    ];

    private string $name;
    private EventDispatcherInterface $dispatcher;
    private MessageBusInterface $bus;
    private SerializerInterface $serializer;

    /** @var array<string, bool> */
    private static array $intercept = [];

    /** @var array<string, bool> */
    private static array $catchExceptions = [];

    /** @var array<string, bool> */
    private static array $testSerialization = [];

    /** @var array<string, Envelope[]> */
    private static array $dispatched = [];

    /** @var array<string, Envelope[]> */
    private static array $acknowledged = [];

    /** @var array<string, Envelope[]> */
    private static array $rejected = [];

    /** @var array<string, Envelope[]> */
    private static array $queue = [];

    /**
     * @internal
     */
    public function __construct(string $name, MessageBusInterface $bus, EventDispatcherInterface $dispatcher, SerializerInterface $serializer, array $options = [])
    {
        $options = \array_merge(self::DEFAULT_OPTIONS, $options);

        $this->name = $name;
        $this->dispatcher = $dispatcher;
        $this->bus = $bus;
        $this->serializer = $serializer;

        self::$intercept[$name] ??= $options['intercept'];
        self::$catchExceptions[$name] ??= $options['catch_exceptions'];
        self::$testSerialization[$name] ??= $options['test_serialization'];
    }

    /**
     * Processes any messages on the queue and processes future messages
     * immediately.
     */
    public function unblock(): self
    {
        if ($this->hasMessagesToProcess()) {
            // process any messages currently on queue
            $this->process();
        }

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
     * Processes messages on the queue. This is done recursively so if handling
     * a message dispatches more messages, these will be processed as well (up
     * to $number).
     *
     * @param int $number the number of messages to process (-1 for all)
     */
    public function process(int $number = -1): self
    {
        $processCount = 0;

        // keep track of added listeners/subscribers so we can remove after
        $listeners = [];

        $this->dispatcher->addListener(
            WorkerRunningEvent::class,
            $listeners[WorkerRunningEvent::class] = static function(WorkerRunningEvent $event) use (&$processCount) {
                if ($event->isWorkerIdle()) {
                    // stop worker if no messages to process
                    $event->getWorker()->stop();

                    return;
                }

                ++$processCount;
            }
        );

        if ($number > 0) {
            // stop if limit was placed on number to process
            $this->dispatcher->addSubscriber($listeners[] = new StopWorkerOnMessageLimitListener($number));
        }

        if (!$this->isCatchingExceptions()) {
            $this->dispatcher->addListener(
                WorkerMessageFailedEvent::class,
                $listeners[WorkerMessageFailedEvent::class] = static function(WorkerMessageFailedEvent $event) {
                    throw $event->getThrowable();
                }
            );
        }

        $worker = new Worker([$this], $this->bus, $this->dispatcher);
        $worker->run(['sleep' => 0]);

        // remove added listeners/subscribers
        foreach ($listeners as $event => $listener) {
            if ($listener instanceof EventSubscriberInterface) {
                $this->dispatcher->removeSubscriber($listener);

                continue;
            }

            $this->dispatcher->removeListener($event, $listener);
        }

        if ($number > 0) {
            Assert::that($processCount)->is($number, 'Expected to process {expected} messages but only processed {actual}.');
        }

        return $this;
    }

    /**
     * Works the same as {@see process()} but fails if no messages on queue.
     */
    public function processOrFail(int $number = -1): self
    {
        Assert::true($this->hasMessagesToProcess(), 'No messages to process.');

        return $this->process($number);
    }

    public function queue(): EnvelopeCollection
    {
        return new EnvelopeCollection($this, ...self::$queue[$this->name] ?? []);
    }

    public function dispatched(): EnvelopeCollection
    {
        return new EnvelopeCollection($this, ...self::$dispatched[$this->name] ?? []);
    }

    public function acknowledged(): EnvelopeCollection
    {
        return new EnvelopeCollection($this, ...self::$acknowledged[$this->name] ?? []);
    }

    public function rejected(): EnvelopeCollection
    {
        return new EnvelopeCollection($this, ...self::$rejected[$this->name] ?? []);
    }

    /**
     * @internal
     */
    public function get(): iterable
    {
        return self::$queue[$this->name] ? [\array_shift(self::$queue[$this->name])] : [];
    }

    /**
     * @internal
     */
    public function ack(Envelope $envelope): void
    {
        self::$acknowledged[$this->name][] = $envelope;
    }

    /**
     * @internal
     */
    public function reject(Envelope $envelope): void
    {
        self::$rejected[$this->name][] = $envelope;
    }

    public function send(Envelope $envelope): Envelope
    {
        if ($this->shouldTestSerialization()) {
            Assert::try(
                fn() => $this->serializer->decode($this->serializer->encode($envelope)),
                'A problem occurred in the serialization process.'
            );
        }

        self::$dispatched[$this->name][] = $envelope;
        self::$queue[$this->name][] = $envelope;

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
        self::$queue[$this->name] = self::$dispatched[$this->name] = self::$acknowledged[$this->name] = self::$rejected[$this->name] = [];
    }

    /**
     * Resets data and options for all transports.
     */
    public static function resetAll(): void
    {
        self::$queue = self::$dispatched = self::$acknowledged = self::$rejected = self::$intercept = self::$catchExceptions = [];
    }

    private function isIntercepting(): bool
    {
        return self::$intercept[$this->name];
    }

    private function isCatchingExceptions(): bool
    {
        return self::$catchExceptions[$this->name];
    }

    private function shouldTestSerialization(): bool
    {
        return self::$testSerialization[$this->name];
    }

    private function hasMessagesToProcess(): bool
    {
        return !empty(self::$queue[$this->name] ?? []);
    }
}
