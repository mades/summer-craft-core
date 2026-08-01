<?php

namespace SummerCraft\Core\EventDispatcher;

interface EventDispatcher
{
    public function subscribe(string $eventName, string $subscriberServiceName): void;

    public function fire(Event $event): void;
}
