<?php

namespace SummerCraft\Core\Tests\Fixture\Cli;

use SummerCraft\Core\Cli\CliHandler;

class RecordingCliHandler implements CliHandler
{
    /** @var string[] */
    public array $received = [];

    public function handle(array $arguments = []): void
    {
        $this->received = $arguments;
    }
}
