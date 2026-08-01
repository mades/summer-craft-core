<?php

namespace SummerCraft\Core;

use ReflectionClass;
use ReflectionException;

class Autoloader
{
    private const CLASSES_CACHE_FILE = 'loadedClasses.php';
    private const CLASSES_CACHE_LOG = 'autoloaderLog.txt';

    private static string $basePath = '';
    private static string $cachePath = '';
    private static array $classPlaces = [];
    private static bool $logMisses = false;

    private ?array $loadedClasses = null;
    

    /**
     * @param bool $logMisses Off by default, and normally should stay off: this
     *        autoloader is prepended before composer's, so every class it does
     *        not map — every PSR interface, every vendor class — reaches it
     *        first and misses. Those misses are the expected division of
     *        labour, not errors, and logging them appends to a file on every
     *        request forever. Turn it on to debug why a class of your own is
     *        not being found, then turn it back off.
     */
    public static function create(
        string $basePath,
        string $cachePath,
        array $classPlaces = [],
        bool $logMisses = false
    ): self {
        self::$basePath = $basePath;
        self::$cachePath = $cachePath;
        self::$classPlaces = $classPlaces;
        self::$logMisses = $logMisses;
        return new self();
    }

    private function __construct() {
    }

    public function setAutoloader(): void
    {
        spl_autoload_register(function (string $class): void {
            $this->loadClass($class);
        }, true, true);
    }

    public function loadClass(string $class): bool
    {
        if ($this->loadedClasses === null) {
            if (file_exists(self::$cachePath . self::CLASSES_CACHE_FILE)) {
                $this->loadedClasses = include self::$cachePath . self::CLASSES_CACHE_FILE;
            }
            if (!is_array($this->loadedClasses)) {
                $this->loadedClasses = [];
            }
        }
        if (isset($this->loadedClasses[$class])) {
            include self::$basePath . $this->loadedClasses[$class];
            if (class_exists($class) || interface_exists($class)) {
                return true;
            }
            if (file_exists(self::$cachePath . self::CLASSES_CACHE_FILE)) {
                // Invalid cache file
                unlink(self::$cachePath . self::CLASSES_CACHE_FILE);
                file_put_contents(
                    self::$cachePath . self::CLASSES_CACHE_LOG,
                    "Invalid cache for class [$class] got[{$this->loadedClasses[$class]}]. File cleared" . ";\n",
                    FILE_APPEND | LOCK_EX
                );
                $this->loadedClasses = [];
            }
        }
        $file = self::getFileFromClassName($class);

        if (!file_exists(self::$basePath . $file)) {
            $this->logMiss("Unknown. File not exists. Class: [$class]. File: [$file]");
            return false;
        }

        include self::$basePath . $file;
        if (!class_exists($class) && !interface_exists($class)) {
            $this->logMiss("Unknown Incorrect class in file. Class: [$class]. File: [$file]");
            return false;

        }

        $this->loadedClasses[$class] = $file;
        return true;
    }

    /**
     * Write via a temp file + rename instead of in place: `include` on line 45 reads the
     * cache file without any lock, so an in-place file_put_contents() (even with LOCK_EX)
     * can hand a concurrent reader a half-written file — rename() is atomic within the
     * same filesystem, so readers only ever see the old or the new complete file.
     */
    public function saveCache(): void
    {
        $target = self::$cachePath . self::CLASSES_CACHE_FILE;
        $tmpFile = $target . '.' . bin2hex(random_bytes(8)) . '.tmp';
        file_put_contents($tmpFile, "<?php\n return " . var_export($this->loadedClasses, true) . ";\n", LOCK_EX);
        rename($tmpFile, $target);
    }

    public function getLoadedClasses(): array
    {
       return $this->loadedClasses ?? [];
    }

    private function logMiss(string $message): void
    {
        if (!self::$logMisses) {
            return;
        }
        file_put_contents(
            self::$cachePath . self::CLASSES_CACHE_LOG,
            $message . ";\n",
            FILE_APPEND | LOCK_EX
        );
    }

    private static function getBasePath(): string
    {
        if (!empty(self::$basePath)) {
            return self::$basePath;
        } elseif (Application::hasInstance()) {
            return Application::getInstance()->getContext()->getBasePath();
        } else {
            throw new \RuntimeException("Can not get base path. Application not created");
        }
    }

    public static function getFullFileFromClassName(string $className): string
    {
        $file = self::getFileFromClassName($className);
        // the reflection fallback already returns an absolute path
        if (str_starts_with($file, DIRECTORY_SEPARATOR)) {
            return $file;
        }
        return self::getBasePath() . $file;
    }

    public static function getFileFromClassName(string $className): string
    {
        $explodedClass = explode('\\', $className);
        if (isset(self::$classPlaces[$explodedClass[0]])) {
            $explodedClass[0] = self::$classPlaces[$explodedClass[0]];
            return implode(DIRECTORY_SEPARATOR, $explodedClass) . '.php';
        }

        foreach (self::$classPlaces as $classStartsWith => $classDestination) {
            if (str_starts_with($className, $classStartsWith)) {
                $resultFile = str_replace($classStartsWith, $classDestination, $className);
                return str_replace('\\', DIRECTORY_SEPARATOR, $resultFile) . '.php';
            }
        }

        try {
            $reflectionClass = new ReflectionClass($className);
            return $reflectionClass->getFileName();
        } catch (ReflectionException $e) {
        }

        return str_replace('\\', DIRECTORY_SEPARATOR, $className) . '.php';
    }
}
