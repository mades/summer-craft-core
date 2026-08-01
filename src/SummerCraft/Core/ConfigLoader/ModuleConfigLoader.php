<?php

namespace SummerCraft\Core\ConfigLoader;

use SummerCraft\Core\ComponentManaging\ComponentHolder;

interface ModuleConfigLoader
{
    public function load(ComponentHolder $componentHolder): void;
}