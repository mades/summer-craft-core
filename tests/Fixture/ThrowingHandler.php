<?php

namespace SummerCraft\Core\Tests\Fixture;

use RuntimeException;
use SummerCraft\Core\ComponentManaging\LifeCycle\RequestScopeComponent;
use SummerCraft\Core\EventDispatcher\Event;
use SummerCraft\Core\EventDispatcher\EventSubscriber;

class ThrowingHandler implements EventSubscriber, RequestScopeComponent
{
    public function catchEvent(Event $event): void
    {
        throw new RuntimeException('boom');
    }
}
