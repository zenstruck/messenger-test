<?php

/*
 * This file is part of the zenstruck/messenger-test package.
 *
 * (c) Kevin Bond <kevinbond@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Zenstruck\Messenger\Test\Tests\TransportsAreResetCorrectly;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Zenstruck\Messenger\Test\Tests\Fixture\Messenger\MessageA;
use Zenstruck\Messenger\Test\Transport\TestTransportRegistry;

/**
 * This test is just made to dispatch a message without using "InteractsWithMessenger" trait.
 * We want to confirm that the next test starts with empty transports.
 */
final class NotInteractsWithMessengerTest extends KernelTestCase
{
    public function test_it_dispatches_a_message(): void
    {
        /** @var TestTransportRegistry $registry */
        $registry = self::getContainer()->get('zenstruck_messenger_test.transport_registry');
        $testTransport = $registry->get();

        $testTransport->queue()->assertCount(0);

        self::getContainer()->get(MessageBusInterface::class)->dispatch(new MessageA());

        $testTransport->queue()->assertCount(0);
    }

    protected static function bootKernel(array $options = []): KernelInterface // @phpstan-ignore-line
    {
        return parent::bootKernel(\array_merge(['environment' => 'single_transport'], $options));
    }
}
