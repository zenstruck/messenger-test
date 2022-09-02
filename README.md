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

## Usage

You can interact with the test transports in your tests by using the
`InteractsWithMessenger` trait in your `KernelTestCase`/`WebTestCase` tests:

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
        $this->messenger()->queue()->assertEmpty();
        $this->messenger()->queue()->assertNotEmpty();
        $this->messenger()->queue()->assertCount(3);
        $this->messenger()->queue()->assertContains(MyMessage::class); // queue contains this message
        $this->messenger()->queue()->assertContains(MyMessage::class, 3); // queue contains this message 3 times
        $this->messenger()->queue()->assertContains(MyMessage::class, 0); // queue contains this message 0 times
        $this->messenger()->queue()->assertNotContains(MyMessage::class); // queue not contains this message

        // access the queue data
        $this->messenger()->queue(); // Envelope[]
        $this->messenger()->queue()->messages(); // object[] the messages unwrapped from envelope
        $this->messenger()->queue()->messages(MyMessage::class); // MyMessage[] just messages matching class
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
        $this->messenger()->queue()->assertCount(3);

        $this->messenger()->process(1); // process one message
        $this->messenger()->processOrFail(1); // equivalent to above but fails if queue empty

        $this->messenger()->queue()->assertCount(2); // queue now only has 2 items

        $this->messenger()->process(); // process all messages on the queue
        $this->messenger()->processOrFail(); // equivalent to above but fails if queue empty

        $this->messenger()->queue()->assertEmpty(); // queue is now empty
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
        $this->messenger()->send(new MyMessage());

        // send with stamps
        $this->messenger()->send(Envelope::wrap(new MyMessage(), [new SomeStamp()]));

        // send "pre-encoded" message
        $this->messenger()->send(['body' => '...']);

        $queue = $this->messenger()->queue();
        $dispatched = $this->messenger()->dispatched();
        $acknowledged = $this->messenger()->acknowledged(); // messages successfully processed
        $rejected = $this->messenger()->rejected(); // messages not successfully processed

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
        $this->messenger()->reset();

        // reset collected messages for all transports
        TestTransport::resetAll();

        // fluid assertions on different EnvelopeCollections
        $this->messenger()
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
        $this->messenger()->throwExceptions();

        // if processing fails, the exception will be thrown
        $this->messenger()->process(1);

        // re-enable exception catching
        $this->messenger()->catchExceptions();
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
        $this->messenger()->unblock();

        // ...some code that routes messages to your configured transport
        // ...these messages are handled immediately

        // enable intercept
        $this->messenger()->intercept();

        // ...some code that routes messages to your configured transport

        // if messages are on the queue when calling unblock(), they are processed
        $this->messenger()->unblock();
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

### Enable Retries

By default, the `TestTransport` does not retry failed messages (your retry settings
are ignored). This behavior can be disabled with the transport dsn:

```yaml
# config/packages/messenger.yaml

# ...

when@test:
    framework:
        messenger:
            transports:
                async: test://?disable_retries=false
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

In your tests, pass the name to the `messenger()` method:

```php
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Messenger\Test\InteractsWithMessenger;

class MyTest extends KernelTestCase // or WebTestCase
{
    use InteractsWithMessenger;

    public function test_something(): void
    {
        $this->messenger('high')->queue();
        $this->messenger('low')->dispatched();
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
