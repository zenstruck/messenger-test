<?php

namespace Zenstruck\Messenger\Test;

use Zenstruck\Messenger\Test\Bus\TestBus;

trait InteractsWithBus
{
    /**
     * @internal
     *
     * @before
     */
    final protected static function _enableMessagesCollection(): void
    {
        TestBus::enableMessagesCollection();
    }

    /**
     * @internal
     *
     * @after
     */
    final protected static function _disableMessagesCollection(): void
    {
        TestBus::disableMessagesCollection();
    }

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
