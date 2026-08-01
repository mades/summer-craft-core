<?php

namespace SummerCraft\Core\Context;

use RuntimeException;

class Yaml
{
    private static array $data = [];

    public static function loadFrom(string $yamlFilePath): void
    {
        $vars = yaml_parse_file($yamlFilePath);
        // array_replace_recursive, not array_merge_recursive: re-loading a key already
        // set by a previous file must overwrite it, not turn the value into an array.
        self::$data = array_replace_recursive(self::$data, $vars);
    }

    public static function getFull(): array
    {
        return self::$data;
    }

    public static function getNullableString(string $path, ?string $default = null): ?string
    {
        [$found, $value] = self::select($path, $default);
        if (!$found) {
            throw new RuntimeException("Path $path in yaml variables not found");
        }
        if ($value !== null && !is_string($value) ) {
            throw new RuntimeException("Path $path in yaml variables is not a nullable string");
        }
        return $value;
    }

    public static function getString(string $path): string
    {
        [$found, $value] = self::select($path);
        if (!$found) {
            throw new RuntimeException("Path $path in yaml variables not found");
        }
        if (!is_string($value) ) {
            throw new RuntimeException("Path $path in yaml variables is not a string");
        }
        return $value;
    }

    public static function findString(string $path, string $default): string
    {
        [$found, $value] = self::select($path, $default);
        if (!$found) {
            return $default;
        }
        if (!is_string($value) ) {
            throw new RuntimeException("Path $path in yaml variables is not a string");
        }
        return $value;
    }

    /**
     * @param string $path Dot- or underscore-separated key path. Because both `.` and `_`
     *                      split the path, a key that itself contains an underscore
     *                      (e.g. `app.some_key`) is not reachable — only `.`-separated
     *                      segments without underscores are.
     */
    private static function select(string $path, $default = null)
    {
        $pathParts = preg_split("/[._]/", $path);

        $found = true;
        $value = self::$data;
        foreach ($pathParts as $part) {
            if (isset($value[$part])) {
                $value = $value[$part];
            } else {
                $found = false;
                $value = $default;
                break;
            }
        }

        return [$found, $value];
    }
}