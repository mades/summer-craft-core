<?php

namespace SummerCraft\Core\EventDispatcher;

interface EventSubscriber
{
    public function catchEvent(Event $event): void;
}
