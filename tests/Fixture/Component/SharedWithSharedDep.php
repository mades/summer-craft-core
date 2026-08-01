<?php

namespace SummerCraft\Core\Tests\Fixture\Component;

use SummerCraft\Core\ComponentManaging\LifeCycle\SharedComponent;

/** Shared component depending on another shared one — legal */
class SharedWithSharedDep implements SharedComponent
{
    public function __construct(public SharedFixture $dep)
    {
    }
}
