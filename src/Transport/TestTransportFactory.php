<?php

namespace Zenstruck\Messenger\Test\Transport;

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
    private MessageBusInterface $bus;

    public function __construct(MessageBusInterface $bus)
    {
        $this->bus = $bus;
    }

    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        return TestTransport::create($options['transport_name'], $this->bus, $serializer, $this->parseDsn($dsn));
    }

    public function supports(string $dsn, array $options): bool
    {
        return 0 === \mb_strpos($dsn, 'test://');
    }

    private function parseDsn(string $dsn): array
    {
        $query = [];

        if ($queryAsString = \mb_strstr($dsn, '?')) {
            \parse_str(\ltrim($queryAsString, '?'), $query);
        }

        return [
            'intercept' => \filter_var($query['intercept'] ?? true, \FILTER_VALIDATE_BOOLEAN),
            'catch_exceptions' => \filter_var($query['catch_exceptions'] ?? true, \FILTER_VALIDATE_BOOLEAN),
        ];
    }
}
