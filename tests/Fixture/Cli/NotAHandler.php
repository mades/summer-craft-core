<?php

namespace SummerCraft\Core\Tests\Fixture\Cli;

/** Buildable by the container, but never written as an entry point */
class NotAHandler
{
    public function handle(array $arguments = []): void
    {
    }
}
