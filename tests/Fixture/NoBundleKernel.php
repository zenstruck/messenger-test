<?php

namespace Zenstruck\Messenger\Test\Tests\Fixture;

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class NoBundleKernel extends Kernel
{
    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
    }
}
