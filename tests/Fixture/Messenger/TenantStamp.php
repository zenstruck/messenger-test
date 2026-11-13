<?php

/*
 * This file is part of the zenstruck/messenger-test package.
 *
 * (c) Kevin Bond <kevinbond@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Zenstruck\Messenger\Test\Tests\Fixture\Messenger;

use Symfony\Component\Messenger\Stamp\PropagatedStampInterface;

final class TenantStamp implements PropagatedStampInterface
{
    public function __construct(private string $tenant)
    {
    }

    public function isFor(string $tenant): bool
    {
        return $this->tenant === $tenant;
    }
}
