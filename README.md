# zenstruck/messenger-test

[![CI Status](https://github.com/zenstruck/messenger-test/workflows/CI/badge.svg)](https://github.com/zenstruck/messenger-test/actions?query=workflow%3ACI)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/zenstruck/messenger-test/badges/quality-score.png?b=1.x)](https://scrutinizer-ci.com/g/zenstruck/messenger-test/?branch=1.x)
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

2. Create a `config/packages/test/messenger.yaml` and override your transport(s)
with `test://`:

    ```yaml
    # config/packages/test/messenger.yaml

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

        $this->messenger()->queue()->assertCount(2); // queue now only has 2 items

        $this->messenger()->process(); // process all messages on the queue

        $this->messenger()->queue()->assertEmpty(); // queue is now empty
    }
}
```

### Other Transport Assertions and Helpers

```php
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Zenstruck\Messenger\Test\InteractsWithMessenger;

class MyTest extends KernelTestCase // or WebTestCase
{
    use InteractsWithMessenger;

    public function test_something(): void
    {
        // ...some code that routes messages to your configured transport

        $queue = $this->messenger()->queued();
        $sent = $this->messenger()->sent();
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
        
        // TestEnvelope stamp assertions
        $queue->first()->assertHasStamp(DelayStamp::class);
        $queue->first()->assertNotHasStamp(DelayStamp::class);
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
        $this->messenger()->throwException();

        // if processing fails, the exception will be thrown
        $this->messenger()->process(1);

        // re-enable exception catching
        $this->messenger()->catchExceptions();
    }
}
```

You can enable exception throwing for your transport(s) by default in the transport dsn:

```yaml
# config/packages/test/messenger.yaml

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
# config/packages/test/messenger.yaml

framework:
    messenger:
        transports:
            async: test://?intercept=false
```

### Multiple Transports

If you have multiple transports you'd like to test, change all their dsn's to
`test://` in your test environment:

```yaml
# config/packages/test/messenger.yaml

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
        $this->messenger('low')->sent();
    }
}
```
