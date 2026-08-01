<?php

namespace SummerCraft\Core\Context;

use RuntimeException;

class Env
{
    public static function loadFromIni(string $iniFilePath): void
    {
        $vars = parse_ini_file($iniFilePath);
        if ($vars === false) {
            throw new RuntimeException("Failed to parse ini file [$iniFilePath]");
        }
        $_ENV = array_merge($vars, $_ENV);
    }

    public static function isset(string $key): bool
    {
        return array_key_exists($key, $_ENV);
    }

    public static function getString(string $key): string
    {
        if (!array_key_exists($key, $_ENV)) {
            throw new RuntimeException("Key $key not found in env variables");
        }
        return (string)$_ENV[$key];
    }

    public static function findString(string $key, string $default): string
    {
        if (!array_key_exists($key, $_ENV)) {
            return $default;
        }
        return (string)$_ENV[$key];
    }

    public static function getArray(string $key): array
    {
        if (!array_key_exists($key, $_ENV)) {
            throw new RuntimeException("Key $key not found in env variables");
        }
        return $_ENV[$key];
    }

    public static function findArray(string $key, array $default): array
    {
        if (!array_key_exists($key, $_ENV)) {
            return $default;
        }
        return $_ENV[$key];
    }
}