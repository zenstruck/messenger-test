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
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 *
 * @internal
 */
final class TestTransportFactory implements TransportFactoryInterface
{
    public function __construct(private MessageBusInterface $bus, private EventDispatcherInterface $dispatcher, private ClockInterface|null $clock = null)
    {
    }

    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface // @phpstan-ignore-line
    {
        return new TestTransport($options['transport_name'], $this->bus, $this->dispatcher, $serializer, $this->clock, $this->parseDsn($dsn));
    }

    public function supports(string $dsn, array $options): bool // @phpstan-ignore-line
    {
        return 0 === \mb_strpos($dsn, 'test://');
    }

    /**
     * @return array<string,bool>
     */
    private function parseDsn(string $dsn): array
    {
        $query = [];

        if ($queryAsString = \mb_strstr($dsn, '?')) {
            \parse_str(\ltrim($queryAsString, '?'), $query);
        }

        return [
            'intercept' => \filter_var($query['intercept'] ?? true, \FILTER_VALIDATE_BOOLEAN),
            'catch_exceptions' => \filter_var($query['catch_exceptions'] ?? true, \FILTER_VALIDATE_BOOLEAN),
            'test_serialization' => \filter_var($query['test_serialization'] ?? true, \FILTER_VALIDATE_BOOLEAN),
            'disable_retries' => \filter_var($query['disable_retries'] ?? true, \FILTER_VALIDATE_BOOLEAN),
            'support_delay_stamp' => \filter_var($query['support_delay_stamp'] ?? false, \FILTER_VALIDATE_BOOLEAN),
        ];
    }
}
