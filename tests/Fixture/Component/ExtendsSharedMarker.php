<?php

namespace SummerCraft\Core\Tests\Fixture\Component;

use SummerCraft\Core\ComponentManaging\LifeCycle\SharedComponent;

/**
 * An interface extending the marker — is_a($name, SharedComponent::class, true) would
 * say true for this name, but it's not a class and can never be instantiated.
 * Regression fixture for ComponentHolder::has().
 */
interface ExtendsSharedMarker extends SharedComponent
{
}
