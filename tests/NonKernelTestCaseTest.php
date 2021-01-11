<?php

namespace Zenstruck\Messenger\Test\Tests;

use PHPUnit\Framework\TestCase;
use Zenstruck\Messenger\Test\InteractsWithQueue;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class NonKernelTestCaseTest extends TestCase
{
    use InteractsWithQueue;

    /**
     * @test
     */
    public function must_extend_kernel_test_case(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('trait can only be used with');

        $this->queue();
    }
}
