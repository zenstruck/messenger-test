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
    /**
     * @param callable|string $filter a callable predicate, or a message class-string
     *
     * @return callable(Envelope):bool
     */
    public static function normalize(callable|string $filter): callable
    {
        if (!\is_callable($filter)) {
            // message class name
            return static fn(Envelope $envelope) => $filter === $envelope->getMessage()::class;
        }

        $function = new \ReflectionFunction($filter(...));

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
