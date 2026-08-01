<?php

namespace SummerCraft\Core\Routing\Resolver;

use Psr\Log\LoggerInterface;
use SummerCraft\Core\Routing\RoutingEntryPoint;

class MethodRoutingResolver implements RoutingResolver
{
    private function __construct(
        private string $controllerClassName,
        private string $methodName,
    ) {
    }

    public static function for(string $className, string $methodName): self
    {
        return new self($className, $methodName);
    }

    public function getRoutingEntryPoint(array $uriMatchData, ?LoggerInterface $debugLogger = null): ?RoutingEntryPoint
    {
        unset($uriMatchData[0]);
        return new RoutingEntryPoint(
            $this->controllerClassName,
            $this->methodName,
            array_values($uriMatchData)
        );
    }
}
