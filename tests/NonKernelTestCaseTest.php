<?php

/*
 * This file is part of the zenstruck/messenger-test package.
 *
 * (c) Kevin Bond <kevinbond@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Zenstruck\Messenger\Test\Tests;

use PHPUnit\Framework\TestCase;
use Zenstruck\Messenger\Test\InteractsWithMessenger;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class NonKernelTestCaseTest extends TestCase
{
    use InteractsWithMessenger;

    /**
     * @test
     */
    public function must_extend_kernel_test_case(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('trait can only be used with');

        $this->messenger();
    }
}
