<?php

namespace SummerCraft\Core\Tests\Fixture\Component;

/**
 * What a registry list is keyed by. Registrations used to be checked against
 * nothing, so the tests keyed them by the string 'SomeInterface' — a name with no
 * class behind it, which is exactly the mistake Registry now refuses.
 */
interface RegisteredContract
{
}
