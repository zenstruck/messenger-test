<?php

namespace Zenstruck\Messenger\Test\Tests;

use PHPUnit\Framework\TestCase;
use Zenstruck\Messenger\Test\InteractsWithTransport;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class NonKernelTestCaseTest extends TestCase
{
    use InteractsWithTransport;

    /**
     * @test
     */
    public function must_extend_kernel_test_case(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('trait can only be used with');

        $this->transport();
    }
}
