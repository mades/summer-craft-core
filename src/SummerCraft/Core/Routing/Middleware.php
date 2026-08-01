<?php

namespace SummerCraft\Core\Routing;

/**
 * Legacy middleware: some logic to run before the controller is created and
 * started. If the run method returns false, then the controller will not
 * start and the accumulated response (redirect, error page) is emitted.
 *
 * New middlewares should implement Psr\Http\Server\MiddlewareInterface
 * instead — both kinds can be mixed in one chain (see MiddlewarePipeline).
 */
interface Middleware
{
    public function run(): bool;
}
