<?php

namespace SummerCraft\Core\Tests\Fixture\Component;

use SummerCraft\Core\ComponentManaging\LifeCycle\TransientComponent;

/** Optional constructor params must be filled with their defaults */
class WithDefaultParam implements TransientComponent
{
    public function __construct(public int $number = 42, public ?string $text = null)
    {
    }
}
