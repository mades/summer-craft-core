<?php

namespace SummerCraft\Core\Tests\Fixture\Routing;

use SummerCraft\Core\ComponentManaging\LifeCycle\RequestScopeComponent;

/** Registered as middleware but does not implement the interface */
class NotAMiddleware implements RequestScopeComponent
{
}
