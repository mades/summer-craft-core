<?php

namespace SummerCraft\Core\Routing\Resolver;

use Psr\Log\LoggerInterface;
use SummerCraft\Core\Routing\RoutingEntryPoint;

interface RoutingResolver
{
    public function getRoutingEntryPoint(array $uriMatchData, ?LoggerInterface $debugLogger = null): ?RoutingEntryPoint;
}
