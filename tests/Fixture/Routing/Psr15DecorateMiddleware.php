<?php

namespace SummerCraft\Core\Tests\Fixture\Routing;

use SummerCraft\Core\ComponentManaging\LifeCycle\RequestScopeComponent;

/** PSR-15 middleware: decorates the inner response after the chain returns */
class Psr15DecorateMiddleware implements \Psr\Http\Server\MiddlewareInterface, RequestScopeComponent
{
    public function process(
        \Psr\Http\Message\ServerRequestInterface $request,
        \Psr\Http\Server\RequestHandlerInterface $handler
    ): \Psr\Http\Message\ResponseInterface {
        return $handler->handle($request)->withHeader('X-Decorated', 'yes');
    }
}
