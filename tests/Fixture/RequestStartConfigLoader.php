<?php

namespace SummerCraft\Core\Tests\Fixture;

use SummerCraft\Core\ConfigLoader\CoreConfigLoader;
use SummerCraft\Core\EventDispatcher\EventsConfig;

/**
 * The smallest loader an Application can boot with: the core defaults, plus one
 * subscriber on request.start.
 */
class RequestStartConfigLoader extends CoreConfigLoader
{
    public function load(): void
    {
        parent::load();

        $eventsConfig = $this->componentHolder->get(EventsConfig::class, null);
        $eventsConfig->events[] = ['request.start', RecordingSubscriber::class];
    }
}
