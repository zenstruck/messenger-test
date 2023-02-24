<?php

declare(strict_types=1);

namespace Zenstruck\Messenger\Test\Tests\TransportsAreResetCorrectly;

/**
 * This test is just made to dispatch a message without using "InteractsWithMessenger" trait.
 * We want to confirm that a test which runs after a test which uses the trait still works.
 */
final class NotInteractsWithMessengerAfterTest extends NotInteractsWithMessengerBeforeTest
{

}
