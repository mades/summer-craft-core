<?php

namespace SummerCraft\Core\Cli;

/**
 * Marks a class as safe to invoke by name from the command line.
 *
 * {@see \SummerCraft\Core\Routing\Resolver\CliHandlerRoutingResolver} turns a
 * command-line argument into a class name, and this interface is what decides
 * whether that class may be run at all: implementing it is a deliberate
 * statement that the class was written as an entry point. Anything else the
 * container can build stays unreachable, however the argument is spelled.
 */
interface CliHandler
{
    /**
     * @param string[] $arguments trailing command-line segments, in order
     */
    public function handle(array $arguments = []): void;
}
