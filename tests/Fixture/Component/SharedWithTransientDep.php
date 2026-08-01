<?php

namespace SummerCraft\Core\Tests\Fixture\Component;

use SummerCraft\Core\ComponentManaging\LifeCycle\SharedComponent;

/** Shared component depending on a transient one — legal, transient is just a fresh instance */
class SharedWithTransientDep implements SharedComponent
{
    public function __construct(public TransientFixture $dep)
    {
    }
}
