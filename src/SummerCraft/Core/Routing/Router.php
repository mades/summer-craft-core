<?php

namespace SummerCraft\Core\Routing;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use SummerCraft\Core\BenchmarkHolder;
use SummerCraft\Core\ComponentManaging\LifeCycle\RequestScopeComponent;
use SummerCraft\Core\ComponentManaging\RequestScope;
use SummerCraft\Core\ExceptionProcessing\ThrowableContext;
use SummerCraft\Core\Http\ResponseAccumulator;
use SummerCraft\Core\Routing\Exception\BadRequestException;
use Throwable;

class Router implements RequestScopeComponent
{
    private LoggerInterface $log;

    public function __construct(
        private RequestScope $requestScope,
        private RouterConfig $config,
        private BenchmarkHolder $benchmark
    ) {
        // Has implemented Psr Logger
        if ($this->requestScope->has(LoggerInterface::class)) {
            $this->log = $this->requestScope->get(LoggerInterface::class);
        }
    }

    public function route(RequestScope $requestScope): void
    {
        try {
            $request = $requestScope->get(ServerRequestInterface::class);
            $segmentsUri = $requestScope->get(UriSegments::class)->uri();

            $patterns = array_merge(
                array_values($this->config->routingPatterns),
                // asked last, whatever order the modules were loaded in
                array_values($this->config->fallbackRoutingPatterns)
            );
            foreach ($patterns as $routingPattern) {
                $routingEntryPoint = $routingPattern->check($request, $segmentsUri);
                if ($routingEntryPoint === null) {
                    continue;
                }

                $this->routeExecute($requestScope, $routingEntryPoint);
                return;
            }

            if (isset($this->log)) {
                $this->log->notice('Client 404 on Page not found: ' . $request->getUri());
                foreach ($patterns as $routingPattern) {
                    $routingEntryPoint = $routingPattern->check($request, $segmentsUri, $this->log);
                    if ($routingEntryPoint === null) {
                        continue;
                    }
                    break;
                }
            }

            $this->routeExecute($requestScope, $this->config->entryPointForError404);

        } catch (BadRequestException $exception) {
            if (isset($this->log)) {
                $this->log->warning('Client Bad Request', [
                    'message' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'fileLine' => $exception->getLine(),
                    'backtrace' => $exception->getTraceAsString(),
                ]);
            }

            $requestScope->get(ThrowableContext::class)->setThrowable($exception);
            $this->routeExecute($requestScope, $this->config->entryPointForError400);
        } catch (Throwable $throwable) {
            if (isset($this->log)) {
                $this->log->warning('Client 500 on Internal server Error', [
                    'message' => $throwable->getMessage(),
                    'file' => $throwable->getFile(),
                    'fileLine' => $throwable->getLine(),
                    'backtrace' => $throwable->getTraceAsString(),
                ]);
            }

            $requestScope->get(ThrowableContext::class)->setThrowable($throwable);
            $this->routeExecute($requestScope, $this->config->entryPointForError500);
        }
    }

    private function routeExecute(RequestScope $requestScope, RoutingEntryPoint $routingEntryPoint): void
    {
        $this->benchmark->point('CreateMiddlewares');

        if ($this->config->globalMiddlewares) {
            $routingEntryPoint->setMiddlewares(array_merge(
                $this->config->globalMiddlewares,
                $routingEntryPoint->getMiddlewareServiceNames()
            ));
        }

        $pipeline = new MiddlewarePipeline($requestScope, $routingEntryPoint, $this->benchmark);
        $response = $pipeline->handle($requestScope->get(ServerRequestInterface::class));

        $requestScope->get(ResponseAccumulator::class)->replaceWith($response);
    }
}
