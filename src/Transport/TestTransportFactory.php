<?php

namespace Zenstruck\Messenger\Test\Transport;

use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 *
 * @internal
 */
final class TestTransportFactory implements TransportFactoryInterface, ResetInterface
{
    private MessageBusInterface $bus;

    /** @var TestTransport[] */
    private array $transports = [];

    public function __construct(MessageBusInterface $bus)
    {
        $this->bus = $bus;
    }

    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        ['intercept' => $intercept] = $this->parseDsn($dsn);

        return $this->transports[$options['transport_name']] = new TestTransport($this->bus, $serializer, $intercept);
    }

    public function get(?string $name = null): TestTransport
    {
        if (0 === \count($this->transports)) {
            throw new \LogicException('No transports registered.');
        }

        if (null === $name && 1 !== \count($this->transports)) {
            throw new \InvalidArgumentException('Multiple transports are registered, you must specify a name.');
        }

        if (null === $name) {
            $name = \array_key_first($this->transports);
        }

        if (!$transport = $this->transports[$name] ?? null) {
            throw new \InvalidArgumentException("Transport \"{$name}\" not registered.");
        }

        if (!$transport instanceof TestTransport) {
            throw new \LogicException("Transport \"{$name}\" needs to be set to \"test://\" in your test config to use this feature.");
        }

        return $transport;
    }

    public function supports(string $dsn, array $options): bool
    {
        return 0 === \mb_strpos($dsn, 'test://');
    }

    public function reset(): void
    {
        foreach ($this->transports as $transport) {
            $transport->reset();
        }
    }

    private function parseDsn(string $dsn): array
    {
        $query = [];

        if ($queryAsString = \mb_strstr($dsn, '?')) {
            \parse_str(\ltrim($queryAsString, '?'), $query);
        }

        return [
            'intercept' => \filter_var($query['intercept'] ?? true, FILTER_VALIDATE_BOOLEAN),
        ];
    }
}
