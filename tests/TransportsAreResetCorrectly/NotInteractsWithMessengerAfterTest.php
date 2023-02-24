<?php

declare(strict_types=1);

/*
 * This file is part of the zenstruck/messenger-test package.
 *
 * (c) Kevin Bond <kevinbond@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Zenstruck\Messenger\Test\Tests\TransportsAreResetCorrectly;

/**
 * This test is just made to dispatch a message without using "InteractsWithMessenger" trait.
 * We want to confirm that a test which runs after a test which uses the trait still works.
 */
final class NotInteractsWithMessengerAfterTest extends NotInteractsWithMessengerBeforeTest
{
}
