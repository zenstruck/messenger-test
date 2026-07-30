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

/**
 * @internal
 */
final class EnvelopeFilter
{
    private \Closure $filter;

    /**
     * @template T of object
     *
     * @param string|callable(T):mixed $filter a message class-string, or a callable predicate receiving
     *                                         the Envelope, the type-hinted message, or no argument
     */
    public function __construct(string|callable $filter)
    {
        $this->filter = $this->normalize($filter);
    }

    public function __invoke(Envelope $envelope): bool
    {
        return (bool) ($this->filter)($envelope);
    }

    /**
     * @param callable|string $filter a callable predicate, or a message class-string
     *
     * @return \Closure(Envelope):bool
     */
    private function normalize(callable|string $filter): \Closure
    {
        if (!\is_callable($filter)) {
            // message class name
            return static fn(Envelope $envelope) => $filter === $envelope->getMessage()::class;
        }

        if (!$filter instanceof \Closure) {
            $filter = $filter(...);
        }

        $function = new \ReflectionFunction($filter);

        if (!$parameter = $function->getParameters()[0] ?? null) {
            return $filter;
        }

        if (!$type = $parameter->getType()) {
            return $filter;
        }

        if (!$type instanceof \ReflectionNamedType || $type->isBuiltin() || Envelope::class === $type->getName()) {
            return $filter;
        }

        // user used message class name as type-hint
        return static function(Envelope $envelope) use ($filter, $type) {
            if ($type->getName() !== $envelope->getMessage()::class) {
                return false;
            }

            return $filter($envelope->getMessage());
        };
    }
}
