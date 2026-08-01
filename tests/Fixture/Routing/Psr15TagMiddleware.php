<?php

namespace SummerCraft\Core\Tests\Fixture\Routing;

use SummerCraft\Core\ComponentManaging\LifeCycle\RequestScopeComponent;

/** PSR-15 middleware: tags the request with an attribute and passes on */
class Psr15TagMiddleware implements \Psr\Http\Server\MiddlewareInterface, RequestScopeComponent
{
    public static int $runs = 0;

    public function process(
        \Psr\Http\Message\ServerRequestInterface $request,
        \Psr\Http\Server\RequestHandlerInterface $handler
    ): \Psr\Http\Message\ResponseInterface {
        self::$runs++;
        return $handler->handle($request->withAttribute('tag', 'tagged'));
    }
}
