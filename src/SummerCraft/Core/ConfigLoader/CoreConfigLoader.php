<?php

namespace SummerCraft\Core\ConfigLoader;

use ReflectionClass;
use RuntimeException;
use SummerCraft\Core\Autoloader;
use SummerCraft\Core\ComponentManaging\ComponentHolder;
use SummerCraft\Core\ComponentManaging\Config\ComponentConfig;
use SummerCraft\Core\ComponentManaging\Config\Config;
use SummerCraft\Core\ComponentManaging\LifeCycle\SharedComponent;
use SummerCraft\Core\Context\ApplicationContext;

abstract class CoreConfigLoader implements SharedComponent
{
    public function __construct(
        protected ComponentHolder $componentHolder,
        protected ApplicationContext $context,
    ) {
    }

    public function load(): void
    {
        $config = $this->componentHolder->get(Config::class, null);
        $config->services[\SummerCraft\Core\EventDispatcher\EventDispatcher::class] = ComponentConfig::forClass(\SummerCraft\Core\EventDispatcher\DefaultEventDispatcher::class);

        // Application::run() sets the real request instance into the scope before
        // anything resolves; this callback is the fallback for bare scopes (tests, CLI)
        $config->services[\Psr\Http\Message\ServerRequestInterface::class] = ComponentConfig::forCallback(
            fn () => \SummerCraft\Core\Http\ServerRequestFactory::fromGlobals(),
            \SummerCraft\Core\Http\ServerRequest::class
        );
    }

    public function initialize(): void
    {
        if (!isset($_SERVER['DOCUMENT_ROOT'])) {
            $_SERVER['DOCUMENT_ROOT'] = $this->context->getPublicPath();
        }
    }

    /**
     * Picks up every Loader.php sitting one directory below the one that holds
     * $modulesRootClass, and lets it contribute to the configuration.
     *
     * Modules are loaded in the order scandir() returns them, which is
     * alphabetical by directory name. Anything that cares about the order —
     * RegistryConfig entries added without an explicit ordering, middleware,
     * routes shadowing one another — depends on those names, so a module that
     * must come first has to be named so rather than rely on where it happens
     * to sit.
     */
    protected function loadModulesConfigs(string $modulesRootClass): void
    {
        $config = $this->componentHolder->get(Config::class, null);
        $modulesRootNamespace = (new ReflectionClass($modulesRootClass))->getNamespaceName();

        $modulesDirectory = dirname(Autoloader::getFullFileFromClassName($modulesRootClass)) . DIRECTORY_SEPARATOR;
        if (!is_dir($modulesDirectory)) {
            throw new RuntimeException(
                "Modules directory [$modulesDirectory], resolved from [$modulesRootClass], does not exist"
            );
        }

        // a plain file cannot hold a Loader.php, so the marker class itself and
        // the dot entries drop out here without being named
        foreach (scandir($modulesDirectory) as $module) {
            if (!file_exists($modulesDirectory . $module . DIRECTORY_SEPARATOR . 'Loader.php')) {
                continue;
            }
            $moduleLoaderClass = $modulesRootNamespace . '\\' . $module . '\\Loader';
            $moduleLoader = $this->componentHolder->get($moduleLoaderClass, null);
            if (!$moduleLoader instanceof ModuleConfigLoader) {
                throw new RuntimeException(
                    'Class ' . $moduleLoaderClass . ' is not an instance of ' . ModuleConfigLoader::class
                );
            }
            $config->moduleLoaders[] = $moduleLoader;
            $moduleLoader->load($this->componentHolder);
        }
    }
}
