<?php

namespace Zenstruck\Messenger\Test;

use Zenstruck\Messenger\Test\Bus\TestBus;

trait InteractsWithBus
{
    /**
     * @internal
     *
     * @after
     */
    final protected static function _resetMessengerBuses(): void
    {
        TestBus::resetAll();
    }
}
