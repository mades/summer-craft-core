<?php

namespace SummerCraft\Core\Tests\Fixture\Component;

use SummerCraft\Core\ComponentManaging\LifeCycle\TransientComponent;

/** Untyped required param — container can not resolve it */
class UntypedParam implements TransientComponent
{
    public function __construct(public $anything)
    {
    }
}
