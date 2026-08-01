<?php

namespace SummerCraft\Core\Tests\Fixture\Component;

/** No lifecycle marker at all — creatable by get() only inside a request scope */
class PlainFixture implements RegisteredContract, OtherRegisteredContract
{
}
