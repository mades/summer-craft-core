<?php

namespace SummerCraft\Core\ComponentManaging\Config;

use Closure;

class ComponentConfig
{
    public string $className;

    public ?Closure $callback = null;

    public static function forCallback(Closure $callback, string $className): self
    {
        $config = new self();
        $config->callback = $callback;
        $config->className = $className;
        return $config;
    }

    public static function forClass(string $className): self
    {
        $config = new self();
        $config->callback = null;
        $config->className = $className;
        return $config;
    }

    public function isCallbackMethodCreation(): bool
    {
        return $this->callback !== null;
    }
}