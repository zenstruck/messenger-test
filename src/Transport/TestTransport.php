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

use Psr\Clock\ClockInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Messenger\Worker;
use Zenstruck\Assert;
use Zenstruck\Messenger\Test\Stamp\AvailableAtStamp;
use Zenstruck\Messenger\Test\TestEnvelope;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class TestTransport implements TransportInterface, ListableReceiverInterface, MessageCountAwareInterface
{
    private const DEFAULT_OPTIONS = [
        'intercept' => true,
        'catch_exceptions' => true,
        'test_serialization' => true,
        'disable_retries' => true,
        'support_delay_stamp' => false,
    ];

    private string $name;
    private EventDispatcherInterface $dispatcher;
    private MessageBusInterface $bus;
    private SerializerInterface $serializer;
    private ?ClockInterface $clock;

    /** @var array<string, bool> */
    private static array $intercept = [];

    /** @var array<string, bool> */
    private static array $catchExceptions = [];

    /** @var array<string, bool> */
    private static array $testSerialization = [];

    /** @var array<string, bool> */
    private static array $disableRetries = [];

    /** @var array<string, bool> */
    private static array $supportDelayStamp = [];

    /** @var array<string, Envelope[]> */
    private static array $dispatched = [];

    /** @var array<string, Envelope[]> */
    private static array $acknowledged = [];

    /** @var array<string, Envelope[]> */
    private static array $rejected = [];

    /** @var array<string, Envelope[]> */
    private static array $queue = [];

    // this setting applies to all transports
    private static bool $enableMessagesCollection = true;

    /**
     * @internal
     *
     * @param array<string,bool> $options
     */
    public function __construct(string $name, MessageBusInterface $bus, EventDispatcherInterface $dispatcher, SerializerInterface $serializer, ?ClockInterface $clock = null, array $options = [])
    {
        $options = \array_merge(self::DEFAULT_OPTIONS, $options);

        $this->name = $name;
        $this->dispatcher = $dispatcher;
        $this->bus = $bus;
        $this->serializer = $serializer;
        $this->clock = $clock;

        self::$intercept[$name] ??= $options['intercept'];
        self::$catchExceptions[$name] ??= $options['catch_exceptions'];
        self::$testSerialization[$name] ??= $options['test_serialization'];
        self::$disableRetries[$name] ??= $options['disable_retries'];
        self::$supportDelayStamp[$name] ??= $options['support_delay_stamp'];

        if (!self::$supportDelayStamp[$name]) {
            trigger_deprecation('zenstruck/messenger-test', '1.8.0', 'Not supporting DelayStamp is deprecated, support will be removed in 2.0.');
        } elseif (!$this->clock) {
            throw new \InvalidArgumentException(\sprintf('A service aliased "%s" must be available in order to support DelayStamp. You can install for instance symfony/clock (composer require symfony/clock).', ClockInterface::class));
        }
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
        $subscribers = [];

        $this->dispatcher->addListener(
            WorkerRunningEvent::class,
            $listeners[WorkerRunningEvent::class] = static function(WorkerRunningEvent $event) use (&$processCount) {
                if ($event->isWorkerIdle()) {
                    // stop worker if no messages to process
                    $event->getWorker()->stop();

                    return;
                }

                ++$processCount;
            },
        );

        if ($number > 0) {
            // stop if limit was placed on number to process
            $this->dispatcher->addSubscriber($subscribers[] = new StopWorkerOnMessageLimitListener($number));
        }

        if (!$this->isCatchingExceptions()) {
            $this->dispatcher->addListener(
                WorkerMessageFailedEvent::class,
                $listeners[WorkerMessageFailedEvent::class] = static function(WorkerMessageFailedEvent $event) {
                    throw $event->getThrowable();
                },
            );
        }

        $worker = new Worker([$this->name => $this], $this->bus, $this->dispatcher);
        $worker->run(['sleep' => 0]);

        // remove added listeners/subscribers
        foreach ($listeners as $event => $listener) {
            $this->dispatcher->removeListener($event, $listener);
        }

        foreach ($subscribers as $subscriber) {
            $this->dispatcher->removeSubscriber($subscriber);
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

    public function queue(): TransportEnvelopeCollection
    {
        return new TransportEnvelopeCollection($this, ...self::$queue[$this->name] ?? []);
    }

    public function dispatched(): TransportEnvelopeCollection
    {
        return new TransportEnvelopeCollection($this, ...self::$dispatched[$this->name] ?? []);
    }

    public function acknowledged(): TransportEnvelopeCollection
    {
        return new TransportEnvelopeCollection($this, ...self::$acknowledged[$this->name] ?? []);
    }

    public function rejected(): TransportEnvelopeCollection
    {
        return new TransportEnvelopeCollection($this, ...self::$rejected[$this->name] ?? []);
    }

    /**
     * @internal
     */
    public function get(): iterable
    {
        if (!isset(self::$queue[$this->name]) || !self::$queue[$this->name]) {
            return [];
        }

        if (!$this->supportsDelayStamp()) {
            return [\array_shift(self::$queue[$this->name])];
        }

        $now = $this->clock->now();

        foreach (self::$queue[$this->name] as $i => $envelope) {
            if (($availableAtStamp = $envelope->last(AvailableAtStamp::class)) && $now < $availableAtStamp->getAvailableAt()) {
                continue;
            }

            unset(self::$queue[$this->name][$i]);

            return [$envelope];
        }

        return [];
    }

    /**
     * @internal
     */
    public function ack(Envelope $envelope): void
    {
        $this->collectMessage(self::$acknowledged, $envelope);
    }

    /**
     * @internal
     */
    public function reject(Envelope $envelope): void
    {
        $this->collectMessage(self::$rejected, $envelope);
    }

    public function all(?int $limit = null): iterable
    {
        return \array_map(
            fn(TestEnvelope $envelope) => $envelope->envelope,
            \array_slice($this->queue()->all(), 0, $limit),
        );
    }

    public function find(mixed $id): ?Envelope
    {
        throw new \BadMethodCallException('Not supported.');
    }

    public function getMessageCount(): int
    {
        return $this->queue()->count();
    }

    /**
     * @param Envelope|object|array{headers?:mixed[],body:string} $what object: will be wrapped in envelope
     *                                                                  array: will be decoded into envelope
     */
    public function send($what): Envelope
    {
        if (\is_array($what)) {
            $what = $this->serializer->decode($what);
        }

        if (!\is_object($what)) {
            throw new \InvalidArgumentException(\sprintf('"%s()" requires a message/Envelope object or decoded message array. "%s" given.', __METHOD__, \get_debug_type($what)));
        }

        $envelope = Envelope::wrap($what);

        if ($this->supportsDelayStamp() && $delayStamp = $envelope->last(DelayStamp::class)) {
            $envelope = $envelope->with(AvailableAtStamp::fromDelayStamp($delayStamp, $this->clock->now()));
        }

        if ($this->isRetriesDisabled() && $envelope->last(RedeliveryStamp::class)) {
            // message is being retried, don't process
            return $envelope;
        }

        if ($this->shouldTestSerialization()) {
            Assert::try(
                fn() => $this->serializer->decode($this->serializer->encode($envelope)),
                'A problem occurred in the serialization process.',
            );
        }

        $this->collectMessage(self::$dispatched, $envelope);
        $this->collectMessage(self::$queue, $envelope, force: true);

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

    public static function initialize(): void
    {
        self::$intercept = self::$catchExceptions = self::$testSerialization = self::$disableRetries = self::$supportDelayStamp = [];
    }

    public static function enableMessagesCollection(): void
    {
        self::$enableMessagesCollection = true;
    }

    public static function disableMessagesCollection(): void
    {
        self::$enableMessagesCollection = false;
    }

    public function isIntercepting(): bool
    {
        return self::$intercept[$this->name];
    }

    public function isCatchingExceptions(): bool
    {
        return self::$catchExceptions[$this->name];
    }

    public function shouldTestSerialization(): bool
    {
        return self::$testSerialization[$this->name];
    }

    public function isRetriesDisabled(): bool
    {
        return self::$disableRetries[$this->name];
    }

    /**
     * @phpstan-assert-if-true !null $this->clock
     */
    public function supportsDelayStamp(): bool
    {
        return $this->clock && self::$supportDelayStamp[$this->name];
    }

    /**
     * @param array<string, Envelope[]> $messagesCollection
     */
    private function collectMessage(array &$messagesCollection, Envelope $envelope, bool $force = false): void
    {
        if (!self::$enableMessagesCollection && !$force) {
            return;
        }

        $messagesCollection[$this->name] ??= [];
        $messagesCollection[$this->name][] = $envelope;
    }

    private function hasMessagesToProcess(): bool
    {
        return !empty(self::$queue[$this->name] ?? []);
    }
}
