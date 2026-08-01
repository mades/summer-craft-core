<?php

namespace SummerCraft\Core\Tests\Fixture\Component;

use SummerCraft\Core\ComponentManaging\LifeCycle\TransientComponent;

/** Transient that autowires a shared dependency */
class NeedsSharedDep implements TransientComponent
{
    public function __construct(public SharedFixture $dep)
    {
    }
}
