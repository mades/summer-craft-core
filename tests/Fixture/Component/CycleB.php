<?php

namespace SummerCraft\Core\Tests\Fixture\Component;

use SummerCraft\Core\ComponentManaging\LifeCycle\SharedComponent;

class CycleB implements SharedComponent
{
    public function __construct(public CycleA $a)
    {
    }
}
