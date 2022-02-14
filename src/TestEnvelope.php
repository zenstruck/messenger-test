<?php

namespace Zenstruck\Messenger\Test;

use Symfony\Component\Messenger\Envelope;
use Zenstruck\Assert;

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

    /**
     * @param string[] $arguments
     *
     * @return mixed
     */
    public function __call(string $name, array $arguments)
    {
        return $this->envelope->{$name}(...$arguments);
    }

    public function assertHasStamp(string $class): self
    {
        Assert::that($this->envelope->all($class))->isNotEmpty(
            'Expected to find stamp "{stamp}" but did not.',
            ['stamp' => $class]
        );

        return $this;
    }

    public function assertNotHasStamp(string $class): self
    {
        Assert::that($this->envelope->all($class))->isEmpty(
            'Expected to not find "{stamp}" but did.',
            ['stamp' => $class]
        );

        return $this;
    }
}
