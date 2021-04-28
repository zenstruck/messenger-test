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

    /** @var array<string, self> */
    private static array $transports = [];

    public function __construct(MessageBusInterface $bus)
    {
        $this->bus = $bus;
    }

    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        return self::$transports[$options['transport_name']] ??= new TestTransport($this->bus, $serializer, $this->parseDsn($dsn));
    }

    public function supports(string $dsn, array $options): bool
    {
        return 0 === \mb_strpos($dsn, 'test://');
    }

    public static function reset(): void
    {
        self::$transports = [];
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
