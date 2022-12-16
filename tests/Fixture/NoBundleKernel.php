<?php

/*
 * This file is part of the zenstruck/messenger-test package.
 *
 * (c) Kevin Bond <kevinbond@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
