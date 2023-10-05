# zenstruck/messenger-test

[![CI Status](https://github.com/zenstruck/messenger-test/workflows/CI/badge.svg)](https://github.com/zenstruck/messenger-test/actions?query=workflow%3ACI)
[![Code Coverage](https://codecov.io/gh/zenstruck/messenger-test/branch/1.x/graph/badge.svg?token=R7OHYYGPKM)](https://codecov.io/gh/zenstruck/messenger-test)

Assertions and helpers for testing your `symfony/messenger` queues.

This library provides a `TestTransport` that, by default, intercepts any messages
sent to it. You can then inspect and assert against these messages. Sent messages
are serialized and unserialized as an added check.

The transport also allows for processing these *queued* messages.

## Installation

1. Install the library:

    ```bash
    composer require --dev zenstruck/messenger-test
    ```

2. Update `config/packages/messenger.yaml` and override your transport(s)
in your `test` environment with `test://`:

    ```yaml
    # config/packages/messenger.yaml

    # ...

    when@test:
        framework:
            messenger:
                transports:
                    async: test://
    ```

## Transport

You can interact with the test transports in your tests by using the
`InteractsWithMessenger` trait in your `KernelTestCase`/`WebTestCase` tests.
You can assert the different steps of message processing by asserting on the queue
and the different states of message processing like "acknowledged", "rejected" and so on.

> **Note**: If you only need to know if a message has been dispatched you can
> make assertions [on the bus itself](#bus).

### Queue Assertions

```php
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Messenger\Test\InteractsWithMessenger;

class MyTest extends KernelTestCase // or WebTestCase
{
    use InteractsWithMessenger;

    public function test_something(): void
    {
        // ...some code that routes messages to your configured transport

        // assert against the queue
        $this->transport()->queue()->assertEmpty();
        $this->transport()->queue()->assertNotEmpty();
        $this->transport()->queue()->assertCount(3);
        $this->transport()->queue()->assertContains(MyMessage::class); // queue contains this message
        $this->transport()->queue()->assertContains(MyMessage::class, 3); // queue contains this message 3 times
        $this->transport()->queue()->assertContains(MyMessage::class, 0); // queue contains this message 0 times
        $this->transport()->queue()->assertNotContains(MyMessage::class); // queue not contains this message

        // access the queue data
        $this->transport()->queue(); // Envelope[]
        $this->transport()->queue()->messages(); // object[] the messages unwrapped from envelope
        $this->transport()->queue()->messages(MyMessage::class); // MyMessage[] just messages matching class
    }
}
```

### Processing The Queue

```php
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Messenger\Test\InteractsWithMessenger;

class MyTest extends KernelTestCase // or WebTestCase
{
    use InteractsWithMessenger;

    public function test_something(): void
    {
        // ...some code that routes messages to your configured transport

        // let's assume 3 messages are on this queue
        $this->transport()->queue()->assertCount(3);

        $this->transport()->process(1); // process one message
        $this->transport()->processOrFail(1); // equivalent to above but fails if queue empty

        $this->transport()->queue()->assertCount(2); // queue now only has 2 items

        $this->transport()->process(); // process all messages on the queue
        $this->transport()->processOrFail(); // equivalent to above but fails if queue empty

        $this->transport()->queue()->assertEmpty(); // queue is now empty
    }
}
```

**NOTE:** Calling `process()` not only processes messages on the queue but any
messages created during the handling of messages (all by default or up to `$number`).

### Other Transport Assertions and Helpers

```php
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Zenstruck\Messenger\Test\InteractsWithMessenger;
use Zenstruck\Messenger\Test\Transport\TestTransport;

class MyTest extends KernelTestCase // or WebTestCase
{
    use InteractsWithMessenger;

    public function test_something(): void
    {
        // manually send a message to your transport
        $this->transport()->send(new MyMessage());

        // send with stamps
        $this->transport()->send(Envelope::wrap(new MyMessage(), [new SomeStamp()]));

        // send "pre-encoded" message
        $this->transport()->send(['body' => '...']);

        $queue = $this->transport()->queue();
        $dispatched = $this->transport()->dispatched();
        $acknowledged = $this->transport()->acknowledged(); // messages successfully processed
        $rejected = $this->transport()->rejected(); // messages not successfully processed

        // The 4 above variables are all instances of Zenstruck\Messenger\Test\EnvelopeCollection
        // which is a countable iterator with the following api (using $queue for the example).
        // Methods that return Envelope(s) actually return TestEnvelope(s) which is an Envelope
        // decorator (all standard Envelope methods can be used) with some stamp-related assertions.

        // collection assertions
        $queue->assertEmpty();
        $queue->assertNotEmpty();
        $queue->assertCount(3);
        $queue->assertContains(MyMessage::class); // contains this message
        $queue->assertContains(MyMessage::class, 3); // contains this message 3 times
        $queue->assertNotContains(MyMessage::class); // not contains this message

        // helpers
        $queue->count(); // number of envelopes
        $queue->all(); // TestEnvelope[]
        $queue->messages(); // object[] the messages unwrapped from their envelope
        $queue->messages(MyMessage::class); // MyMessage[] just instances of the passed message class

        // get specific envelope
        $queue->first(); // TestEnvelope - first one on the collection
        $queue->first(MyMessage::class); // TestEnvelope - first where message class is MyMessage
        $queue->first(function(Envelope $e) {
            return $e->getMessage() instanceof MyMessage && $e->getMessage()->isSomething();
        }); // TestEnvelope - first that matches the filter callback

        // Equivalent to above - use the message class as the filter function typehint to
        // auto-filter to this message type.
        $queue->first(fn(MyMessage $m) => $m->isSomething()); // TestEnvelope

        // TestEnvelope stamp assertions
        $queue->first()->assertHasStamp(DelayStamp::class);
        $queue->first()->assertNotHasStamp(DelayStamp::class);

        // reset collected messages on the transport
        $this->transport()->reset();

        // reset collected messages for all transports
        TestTransport::resetAll();

        // fluid assertions on different EnvelopeCollections
        $this->transport()
            ->queue()
                ->assertNotEmpty()
                ->assertContains(MyMessage::class)
            ->back() // returns to the TestTransport
            ->dispatched()
                ->assertEmpty()
            ->back()
            ->acknowledged()
                ->assertEmpty()
            ->back()
            ->rejected()
                ->assertEmpty()
            ->back()
        ;
    }
}
```

### Processing Exceptions

By default, when processing a message that fails, the `TestTransport` catches
the exception and adds to the rejected list. You can change this behaviour:

```php
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Messenger\Test\InteractsWithMessenger;

class MyTest extends KernelTestCase // or WebTestCase
{
    use InteractsWithMessenger;

    public function test_something(): void
    {
        // ...some code that routes messages to your configured transport

        // disable exception catching
        $this->transport()->throwExceptions();

        // if processing fails, the exception will be thrown
        $this->transport()->process(1);

        // re-enable exception catching
        $this->transport()->catchExceptions();
    }
}
```

You can enable exception throwing for your transport(s) by default in the transport dsn:

```yaml
# config/packages/messenger.yaml

# ...

when@test:
    framework:
        messenger:
            transports:
                async: test://?catch_exceptions=false
 ```

### Unblock Mode

By default, messages sent to the `TestTransport` are intercepted and added to a
queue, waiting to be processed manually. You can change this behaviour so messages
are handled as they are sent:

```php
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Messenger\Test\InteractsWithMessenger;

class MyTest extends KernelTestCase // or WebTestCase
{
    use InteractsWithMessenger;

    public function test_something(): void
    {
        // disable intercept
        $this->transport()->unblock();

        // ...some code that routes messages to your configured transport
        // ...these messages are handled immediately

        // enable intercept
        $this->transport()->intercept();

        // ...some code that routes messages to your configured transport

        // if messages are on the queue when calling unblock(), they are processed
        $this->transport()->unblock();
    }
}
```

You can disable intercepting messages for your transport(s) by default in the transport dsn:

```yaml
# config/packages/messenger.yaml

# ...

when@test:
    framework:
        messenger:
            transports:
                async: test://?intercept=false
```

### Testing Serialization

By default, the `TestTransport` tests that messages can be serialized and deserialized.
This behavior can be disabled with the transport dsn:

```yaml
# config/packages/messenger.yaml

# ...

when@test:
    framework:
        messenger:
            transports:
                async: test://?test_serialization=false
```

### Multiple Transports

If you have multiple transports you'd like to test, change all their dsn's to
`test://` in your test environment:

```yaml
# config/packages/messenger.yaml

# ...

when@test:
    framework:
        messenger:
            transports:
                low: test://
                high: test://
```

In your tests, pass the name to the `transport()` method:

```php
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Messenger\Test\InteractsWithMessenger;

class MyTest extends KernelTestCase // or WebTestCase
{
    use InteractsWithMessenger;

    public function test_something(): void
    {
        $this->transport('high')->queue();
        $this->transport('low')->dispatched();
    }
}
```

### Support of `DelayStamp`

Support of `DelayStamp` could be enabled per transport, within its dsn:

```yaml
# config/packages/messenger.yaml

when@test:
    framework:
        messenger:
            transports:
                async: test://?support_delay_stamp=true
```

> [!NOTE]
> Support of delay stamp was added in version 1.8.0.

#### Usage of a clock

> [!WARNING]
> Support of delay stamp needs an implementation of [PSR-20 Clock](https://www.php-fig.org/psr/psr-20/).

You can, for example use Symfony's clock component:
```bash
composer require symfony/clock
```

When using Symfony's clock component, the service will be automatically configured.
Otherwise, you need to configure it manually:

```yaml
# config/services.yaml
services:
    app.clock:
        class: Some\Clock\Implementation
    Psr\Clock\ClockInterface: '@app.clock'
```

#### Example of code supporting `DelayStamp`

> [!NOTE]
> This example uses `symfony/clock` component, but you can use any other implementation of `Psr\Clock\ClockInterface`.

```php

// Let's say somewhere in your app, you register some actions that should occur in the future:

$bus->dispatch(new Enevelope(new TakeSomeAction1(), [DelayStamp::delayFor(new \DateInterval('P1D'))])); // will be handled in 1 day
$bus->dispatch(new Enevelope(new TakeSomeAction2(), [DelayStamp::delayFor(new \DateInterval('P3D'))])); // will be handled in 3 days

// In your test, you can check that the action is not yet performed:

class TestDelayedActions extends KernelTestCase
{
    use InteractsWithMessenger;
    use ClockSensitiveTrait;

    public function testDelayedActions(): void
    {
        // 1. mock the clock, in order to perform sleeps
        $clock = self::mockTime();

        // 2. trigger the action that will dispatch the two messages

        // ...

        // 3. assert nothing happens yet
        $transport=$this->transport('async');

        $transport->process();
        $transport->queue()->assertCount(2);
        $transport->acknowledged()->assertCount(0);

        // 4. sleep, process queue, and assert some messages have been handled
        $clock->sleep(60 * 60 * 24); // wait one day
        $transport->process()->acknowledged()->assertContains(TakeSomeAction1::class);
        $this->asssertTakeSomeAction1IsHandled();

        // TakeSomeAction2 is still in the queue
        $transport->queue()->assertCount(1);

        $clock->sleep(60 * 60 * 24 * 2); // wait two other days
        $transport->process()->acknowledged()->assertContains(TakeSomeAction2::class);
        $this->asssertTakeSomeAction2IsHandled();
    }
}
```

#### `DelayStamp` and unblock mode

"delayed" messages cannot be handled by the unblocking mechanism, `$transport->process()` must be called after a
`sleep()` has been made.

### Enable Retries

By default, the `TestTransport` does not retry failed messages (your retry settings
are ignored). This behavior can be disabled with the transport dsn:

```yaml
# config/packages/messenger.yaml

when@test:
    framework:
        messenger:
            transports:
                async: test://?disable_retries=false
```

> [!NOTE]
> When using retries along with `support_delay_stamp` you must mock the time to sleep between retries.


## Bus

In addition to transport testing you also can make assertions on the bus. You can test message
handling by using the same `InteractsWithMessenger` trait in your `KernelTestCase` / `WebTestCase` tests.
This is especially useful when you only need to test if a message has been dispatched
by a specific bus but don't need to know how the handling has been made.

It allows you to use your custom transport while asserting your messages are still dispatched properly.

### Single bus

```php
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Messenger\Test\InteractsWithMessenger;

class MyTest extends KernelTestCase
{
    use InteractsWithMessenger;

    public function test_something(): void
    {
        // ... some code that uses the bus

        // Let's assume two messages are processed
        $this->bus()->dispatched()->assertCount(2);

        $this->bus()->dispatched()->assertContains(MessageA::class, 1);
        $this->bus()->dispatched()->assertContains(MessageB::class, 1);
    }
}
```

### Multiple buses

If you use multiple buses you can test that a specific bus has handled its own
messages.

```yaml
# config/packages/messenger.yaml

# ...

framework:
    messenger:
        default_bus: bus_c
        buses:
            bus_a: ~
            bus_b: ~
            bus_c: ~
```

In your tests, pass the name to the `bus()` method:

```php
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Messenger\Test\InteractsWithMessenger;

class MyTest extends KernelTestCase
{
    use InteractsWithMessenger;

    public function test_something(): void
    {
        // ... some code that use bus

        // Let's assume two messages are handled by two different buses
        $this->bus('bus-a')->dispatched()->assertCount(1);
        $this->bus('bus-b')->dispatched()->assertCount(1);
        $this->bus('bus-c')->dispatched()->assertCount(0);

        $this->bus('bus-a')->dispatched()->assertContains(MessageA::class, 1);
        $this->bus('bus-b')->dispatched()->assertContains(MessageB::class, 1);
    }
}
```

## Troubleshooting

### Detached Doctrine Entities

When processing messages in your tests that interact with Doctrine entities you may
notice they become detached from the object manager after processing. This is because
of [`DoctrineClearEntityManagerWorkerSubscriber`](https://github.com/symfony/symfony/blob/0e9cfc38e81464d9394ac6fa061e7962a6fe485d/src/Symfony/Bridge/Doctrine/Messenger/DoctrineClearEntityManagerWorkerSubscriber.php)
which clears the object managers after a message is processed. Currently, the only
way to disable this functionality is to disable the service in your `test` environment:

```yaml
# config/packages/messenger.yaml

# ...

when@test:
    # ...

    services:
        # DoctrineClearEntityManagerWorkerSubscriber service
        doctrine.orm.messenger.event_subscriber.doctrine_clear_entity_manager:
            class: stdClass # effectively disables this service in your test env
```
