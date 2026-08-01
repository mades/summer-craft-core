<?php

namespace SummerCraft\Core\ComponentManaging\Config;

use SummerCraft\Core\ComponentManaging\LifeCycle\SharedComponent;
use SummerCraft\Core\ConfigLoader\ModuleConfigLoader;

class Config implements SharedComponent
{
    /**
     * @var ComponentConfig[] <ServiceName, ServiceConfig>
     */
    public array $services = [];

    /**
     * @var ModuleConfigLoader[]
     */
    public array $moduleLoaders = [];

}
