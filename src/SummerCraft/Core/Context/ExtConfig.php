<?php

namespace SummerCraft\Core\Context;

class ExtConfig
{
    public static function getString(string $path): string
    {
        $envKey = strtoupper($path);
        if (Env::isset($envKey)) {
            return Env::getString($envKey);
        }
        return Yaml::getString(strtolower($path));
    }

    public static function findString(string $path, string $default): string
    {
        $envKey = strtoupper($path);
        if (Env::isset($envKey)) {
            return Env::getString($envKey);
        }
        return Yaml::findString(strtolower($path), $default);
    }
}