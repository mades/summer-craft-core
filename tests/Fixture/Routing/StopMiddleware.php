<?php

namespace SummerCraft\Core\Tests\Fixture\Routing;

use SummerCraft\Core\ComponentManaging\LifeCycle\RequestScopeComponent;
use SummerCraft\Core\Routing\Middleware;

class StopMiddleware implements Middleware, RequestScopeComponent
{
    public static int $runs = 0;

    public function run(): bool
    {
        self::$runs++;
        return false;
    }
}
