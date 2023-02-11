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
use Zenstruck\Messenger\Test\InteractsWithMessenger;

/**
 * This test runs after NotInteractsWithMessengerTest and asserts the message dispatched there, without using InteractsWithMessenger
 * is not present anymore in the test transport.
 */
final class AfterNotInteractsWithMessengerTest extends KernelTestCase
{
    use InteractsWithMessenger;

    /**
     * @test
     */
    public function assert_transports_are_reset_after_a_test_which_does_not_use_trait(): void
    {
        self::bootKernel();

        $this->transport()->queue()->assertCount(0);
        $this->transport()->dispatched()->assertCount(0);
        $this->transport()->acknowledged()->assertCount(0);
        $this->transport()->rejected()->assertCount(0);

        $this->bus()->dispatched()->assertCount(0);
    }

    protected static function bootKernel(array $options = []): KernelInterface // @phpstan-ignore-line
    {
        return parent::bootKernel(\array_merge(['environment' => 'single_transport'], $options));
    }
}
