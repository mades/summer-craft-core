<?php

namespace SummerCraft\Core\Tests\Fixture\Routing;

use SummerCraft\Core\ComponentManaging\LifeCycle\RequestScopeComponent;

/** PSR-15 middleware: short-circuits with its own response */
class Psr15ShortCircuitMiddleware implements \Psr\Http\Server\MiddlewareInterface, RequestScopeComponent
{
    public static int $runs = 0;

    public function process(
        \Psr\Http\Message\ServerRequestInterface $request,
        \Psr\Http\Server\RequestHandlerInterface $handler
    ): \Psr\Http\Message\ResponseInterface {
        self::$runs++;
        return \SummerCraft\Core\Http\Response::html('<p>short-circuit</p>', 403);
    }
}
