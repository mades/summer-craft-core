<?php

namespace SummerCraft\Core\EventDispatcher;

class SimpleEvent implements Event
{
    public function __construct(
        private string $eventName,
        private array $data,
    ) {
    }

    public function getEventName(): string
    {
        return $this->eventName;
    }

    public function getData(): array
    {
        return $this->data;
    }
}
