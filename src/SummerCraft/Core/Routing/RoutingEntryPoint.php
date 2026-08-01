<?php

namespace SummerCraft\Core\Routing;

class RoutingEntryPoint
{
    /**
     * @var string[]
     */
    private array $middlewareServiceNames = [];

    /**
     * @param mixed[] $methodParams spread into the method call, in order —
     *        usually URI segments, but a resolver may pass any shape the
     *        method it points at accepts
     */
    public function __construct(
        private string $controllerName,
        private string $methodName,
        private array $methodParams,
    ) {
    }

    /**
     * @param string[] $middlewareServiceNames
     */
    public function setMiddlewares(array $middlewareServiceNames): void
    {
        $this->middlewareServiceNames = $middlewareServiceNames;
    }

    /**
     * @return string[]
     */
    public function getMiddlewareServiceNames(): array
    {
        return $this->middlewareServiceNames;
    }

    public function getControllerName(): string
    {
        return $this->controllerName;
    }

    public function getMethodName(): string
    {
        return $this->methodName;
    }

    /**
     * @return mixed[]
     */
    public function getMethodParams(): array
    {
        return $this->methodParams;
    }
}
