# zenstruck/messenger-test

[![CI Status](https://github.com/zenstruck/messenger-test/workflows/CI/badge.svg)](https://github.com/zenstruck/messenger-test/actions?query=workflow%3ACI)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/zenstruck/messenger-test/badges/quality-score.png?b=1.x)](https://scrutinizer-ci.com/g/zenstruck/callback/?branch=1.x)
[![Code Coverage](https://codecov.io/gh/zenstruck/messenger-test/branch/1.x/graph/badge.svg?token=R7OHYYGPKM)](https://codecov.io/gh/zenstruck/messenger-test)

Assertions and helpers for testing your `symfony/messenger` queues.

This library provides a `TestTransport` that, by default, intercepts any messages
sent to it. You can then inspect and assert against these messages.

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
        self::bootKernel(); // kernel must be booted before interacting
        
        // ...some code that routes messages to your configured transport

        // assert against the queue
        $this->transport()->queue()->assertEmpty(); 
        $this->transport()->queue()->assertNotEmpty(); 
        $this->transport()->queue()->assertCount(3);
        $this->transport()->queue()->assertContains(MyMessage::class); // queue contains this message
        $this->transport()->queue()->assertContains(MyMessage::class, 3); // queue contains this message 3 times
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
        self::bootKernel(); // kernel must be booted before interacting
        
        // ...some code that routes messages to your configured transport

        // let's assume 3 messages are on this queue
        $this->transport()->queue()->assertCount(3);

        $this->transport()->process(1); // process one message

        $this->transport()->queue()->assertCount(2); // queue now only has 2 items

        $this->transport()->process(); // process all messages on the queue

        $this->transport()->queue()->assertEmpty(); // queue is now empty
    }
}
```

### Other Transport Assertions

```php
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Messenger\Test\InteractsWithMessenger;

class MyTest extends KernelTestCase // or WebTestCase
{
    use InteractsWithMessenger;

    public function test_something(): void
    {
        self::bootKernel(); // kernel must be booted before interacting
        
        // ...some code that routes messages to your configured transport

        // assert against the sent messages
        $this->transport()->sent()->assertEmpty(); 
        $this->transport()->sent()->assertNotEmpty(); 
        $this->transport()->sent()->assertCount(3);
        $this->transport()->sent()->assertContains(MyMessage::class); // contains this message
        $this->transport()->sent()->assertContains(MyMessage::class, 3); // contains this message 3 times
        $this->transport()->sent()->assertNotContains(MyMessage::class); // not contains this message

        // assert against the acknowledged messages
        // these are messages that were successfully processed
        $this->transport()->acknowledged()->assertEmpty(); 
        $this->transport()->acknowledged()->assertNotEmpty(); 
        $this->transport()->acknowledged()->assertCount(3);
        $this->transport()->acknowledged()->assertContains(MyMessage::class); // contains this message
        $this->transport()->acknowledged()->assertContains(MyMessage::class, 3); // contains this message 3 times
        $this->transport()->acknowledged()->assertNotContains(MyMessage::class); // not contains this message

        // assert against the rejected messages
        // these are messages were not successfully processed
        $this->transport()->rejected()->assertEmpty(); 
        $this->transport()->rejected()->assertNotEmpty(); 
        $this->transport()->rejected()->assertCount(3);
        $this->transport()->rejected()->assertContains(MyMessage::class); // contains this message
        $this->transport()->rejected()->assertContains(MyMessage::class, 3); // contains this message 3 times
        $this->transport()->rejected()->assertNotContains(MyMessage::class); // not contains this message
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
        self::bootKernel(); // kernel must be booted before interacting
        
        // ...some code that routes messages to your configured transport

        // disable exception catching
        $this->transport()->throwException();

        // if processing fails, the exception will be thrown
        $this->transport()->process(1);

        // re-enable exception catching
        $this->transport()->catchExceptions();
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
        self::bootKernel(); // kernel must be booted before interacting

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

In your tests, pass the name to the `transport()` method:

```php
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Messenger\Test\InteractsWithMessenger;

class MyTest extends KernelTestCase // or WebTestCase
{
    use InteractsWithMessenger;

    public function test_something(): void
    {
        self::bootKernel(); // kernel must be booted before interacting

        $this->transport('high')->queue();
        $this->transport('low')->sent();
    }
}
```
