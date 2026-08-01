<?php

namespace SummerCraft\Core\EventDispatcher;

interface Event
{
    public function getEventName(): string;
}
