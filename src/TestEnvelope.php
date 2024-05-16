<?php

/*
 * This file is part of the zenstruck/messenger-test package.
 *
 * (c) Kevin Bond <kevinbond@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Zenstruck\Messenger\Test;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Zenstruck\Assert;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 *
 * @mixin Envelope
 */
final class TestEnvelope
{
    public function __construct(public readonly Envelope $envelope)
    {
    }

    /**
     * @param mixed[] $arguments
     *
     * @return mixed
     */
    public function __call(string $name, array $arguments)
    {
        return $this->envelope->{$name}(...$arguments);
    }

    /**
     * @param class-string<StampInterface> $class
     */
    public function assertHasStamp(string $class): self
    {
        Assert::that($this->envelope->all($class))->isNotEmpty(
            'Expected to find stamp "{stamp}" but did not.',
            ['stamp' => $class],
        );

        return $this;
    }

    /**
     * @param class-string<StampInterface> $class
     */
    public function assertNotHasStamp(string $class): self
    {
        Assert::that($this->envelope->all($class))->isEmpty(
            'Expected to not find "{stamp}" but did.',
            ['stamp' => $class],
        );

        return $this;
    }
}
