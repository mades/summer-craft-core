<?php

namespace SummerCraft\Core\Tests\Fixture;

use SummerCraft\Core\ConfigLoader\CoreConfigLoader;
use SummerCraft\Core\EventDispatcher\EventsConfig;

/**
 * Fails a request in the one place Router cannot catch it: a request.start
 * subscriber, which runs before routing begins. Anything thrown inside routing is
 * handled there and turned into a 500 page, so it never reaches run().
 */
class ThrowingConfigLoader extends CoreConfigLoader
{
    public function load(): void
    {
        parent::load();

        $eventsConfig = $this->componentHolder->get(EventsConfig::class, null);
        $eventsConfig->events[] = ['request.start', ThrowingHandler::class];
    }
}
