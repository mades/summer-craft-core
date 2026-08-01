<?php

namespace SummerCraft\Core\Tests\Fixture\Component;

use SummerCraft\Core\ComponentManaging\LifeCycle\SharedComponent;

/** Shared component illegally depending on a request-scoped one */
class SharedWithScopedDep implements SharedComponent
{
    public function __construct(public ScopedFixture $dep)
    {
    }
}
