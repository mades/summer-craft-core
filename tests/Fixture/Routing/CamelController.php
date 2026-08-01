<?php

namespace SummerCraft\Core\Tests\Fixture\Routing;

/** Plain controller for resolver tests (no DI involved) */
class CamelController
{
    public function defaultAction(): void
    {
    }

    public function helloAction(string $a = '', string $b = ''): void
    {
    }

    public function some__Hyphen__partAction(): void
    {
    }
}
