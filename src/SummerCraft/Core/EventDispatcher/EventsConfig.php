<?php

namespace SummerCraft\Core\EventDispatcher;

use SummerCraft\Core\ComponentManaging\LifeCycle\SharedComponent;

class EventsConfig implements SharedComponent
{
    /**
     * @var array<int, array{string, string}> List of [eventName, subscriberServiceName]
     */
    public array $events = [];
}