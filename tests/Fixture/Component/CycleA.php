<?php

namespace SummerCraft\Core\Tests\Fixture\Component;

use SummerCraft\Core\ComponentManaging\LifeCycle\SharedComponent;

class CycleA implements SharedComponent
{
    public function __construct(public CycleB $b)
    {
    }
}
