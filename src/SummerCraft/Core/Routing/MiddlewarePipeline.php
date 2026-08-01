<?php

namespace SummerCraft\Core\Routing;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use SummerCraft\Core\BenchmarkHolder;
use SummerCraft\Core\ComponentManaging\RequestScope;
use SummerCraft\Core\Http\ResponseAccumulator;

/**
 * PSR-15 handler that walks the entry point middleware list and executes
 * the controller action at the end of the chain.
 *
 * A middleware service may implement either the PSR-15 MiddlewareInterface
 * or the legacy framework Middleware (run(): bool). A legacy middleware
 * returning false stops the chain; whatever it accumulated (redirect,
 * error page) becomes the response.
 */
final class MiddlewarePipeline implements RequestHandlerInterface
{
    /** @var string[] */
    private array $middlewareServiceNames;

    public function __construct(
        private RequestScope $requestScope,
        private RoutingEntryPoint $entryPoint,
        private BenchmarkHolder $benchmark,
        private int $position = 0,
    ) {
        $this->middlewareServiceNames = array_values($entryPoint->getMiddlewareServiceNames());
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // a middleware may pass a modified request down the chain: keep the
        // request-scoped instance in sync for services resolved later
        if ($this->requestScope->get(ServerRequestInterface::class) !== $request) {
            $this->requestScope->set(ServerRequestInterface::class, $request);
        }

        if (!isset($this->middlewareServiceNames[$this->position])) {
            return $this->executeController();
        }

        $middlewareServiceName = $this->middlewareServiceNames[$this->position];
        $middleware = $this->requestScope->get($middlewareServiceName);
        $next = new self($this->requestScope, $this->entryPoint, $this->benchmark, $this->position + 1);

        if ($middleware instanceof MiddlewareInterface) {
            return $middleware->process($request, $next);
        }

        if ($middleware instanceof Middleware) {
            if (!$middleware->run()) {
                return $this->requestScope->get(ResponseAccumulator::class)->toResponse();
            }
            return $next->handle($request);
        }

        throw new RuntimeException(sprintf(
            'Middleware service [%s] should implement [%s] or [%s]',
            $middlewareServiceName,
            MiddlewareInterface::class,
            Middleware::class
        ));
    }

    private function executeController(): ResponseInterface
    {
        $this->benchmark->point('CreateController');

        $controller = $this->requestScope->get($this->entryPoint->getControllerName());

        $this->benchmark->point('RunControllerAction');

        $controllerMethod = $this->entryPoint->getMethodName();
        $result = $controller->$controllerMethod(...$this->entryPoint->getMethodParams());

        $this->benchmark->point('EndControllerAction');

        $accumulator = $this->requestScope->get(ResponseAccumulator::class);

        // an action may return a full PSR-7 response instead of using the accumulator
        if ($result instanceof ResponseInterface) {
            $accumulator->replaceWith($result);
        }

        return $accumulator->toResponse();
    }
}
