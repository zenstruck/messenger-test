<?php

namespace Zenstruck\Messenger\Test;

use PHPUnit\Framework\Assert as PHPUnit;
use Symfony\Component\Messenger\Envelope;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 *
 * @mixin Envelope
 */
final class TestEnvelope
{
    private Envelope $envelope;

    public function __construct(Envelope $envelope)
    {
        $this->envelope = $envelope;
    }

    public function __call($name, $arguments)
    {
        return $this->envelope->{$name}(...$arguments);
    }

    public function assertHasStamp(string $class): self
    {
        PHPUnit::assertNotEmpty($this->envelope->all($class));

        return $this;
    }

    public function assertNotHasStamp(string $class): self
    {
        PHPUnit::assertEmpty($this->envelope->all($class));

        return $this;
    }
}
