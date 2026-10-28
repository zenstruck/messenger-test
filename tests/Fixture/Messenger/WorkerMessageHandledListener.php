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

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
#[AsEventListener]
final class WorkerMessageHandledListener
{
    /** @var object[] */
    public array $messages = [];

    public function __invoke(WorkerMessageHandledEvent $event): void
    {
        $this->messages[] = $event->getEnvelope()->getMessage();
    }
}
