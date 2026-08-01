<?php

namespace SummerCraft\Core\Bootstrap;

use SummerCraft\Core\Application;
use SummerCraft\Core\Autoloader;
use SummerCraft\Core\Context\ApplicationContext;

/**
 * Reduces an application's boot file to the only things that differ between
 * applications: where the project root is, and which config loader to use.
 * Wiring the error handlers, building the context, running and emitting are
 * identical everywhere, so they live here — a fix reaches every application
 * through a composer update instead of being applied to each copied boot file.
 *
 * Typical use, from a file two levels below the project root:
 *
 *     $basePath = dirname(__DIR__, 2) . '/';
 *     require $basePath . 'vendor/autoload.php';
 *     return AppBootstrap::create($basePath, \App\Config\ProductionConfigLoader::class);
 *
 * with the entry points calling runHttp() or runCli($argv) on the result.
 * Loading composer's autoloader stays with the application: nothing here can
 * be referenced before it runs.
 */
final class AppBootstrap
{
    private ?Autoloader $builtinAutoloader = null;

    private ?string $publicPath = null;

    private ?string $temporaryPath = null;

    private ?string $resourcePath = null;

    /**
     * @param class-string $configLoader
     */
    private function __construct(
        private readonly string $basePath,
        private readonly string $configLoader,
    ) {
    }

    /**
     * @param class-string $configLoader
     */
    public static function create(string $basePath, string $configLoader): self
    {
        return new self(rtrim($basePath, '/\\') . '/', $configLoader);
    }

    /**
     * Only for layouts that depart from the defaults ApplicationContext derives
     * from basePath (public_html/, storage/temp/, src/resource/).
     */
    public function withPaths(
        ?string $publicPath = null,
        ?string $temporaryPath = null,
        ?string $resourcePath = null,
    ): self {
        $this->publicPath = $publicPath;
        $this->temporaryPath = $temporaryPath;
        $this->resourcePath = $resourcePath;

        return $this;
    }

    /**
     * Opt in to the framework's own class->file cache, layered on top of
     * composer's autoloader (it is prepended, so it answers first and composer
     * picks up whatever it does not map). Off unless asked for — composer's
     * optimized autoloader covers the same ground with fewer moving parts.
     *
     * Map every namespace the application owns. Anything left unmapped still
     * works through composer, just a step later.
     *
     * @param array<string, string> $classPlaces namespace prefix => path relative to basePath
     * @param bool $logMisses see {@see Autoloader::create()} — a debugging aid,
     *        not something to leave on
     */
    public function withBuiltinAutoloader(
        array $classPlaces,
        ?string $cachePath = null,
        bool $logMisses = false
    ): self {
        $this->builtinAutoloader = Autoloader::create(
            $this->basePath,
            $cachePath ?? $this->basePath . 'storage/framework/cache/',
            $classPlaces,
            $logMisses,
        );
        $this->builtinAutoloader->setAutoloader();

        return $this;
    }

    public function runHttp(): void
    {
        $application = $this->boot(isCli: false);
        $application->send($application->run(null));
    }

    /**
     * @param string[] $argv the entry script's own $argv — its first element,
     *        the script name, is dropped
     */
    public function runCli(array $argv): void
    {
        // a console entry point reachable over HTTP would hand out whatever the
        // console can do, unauthenticated
        if (isset($_SERVER['REMOTE_ADDR'])) {
            exit('Permission denied.');
        }

        array_shift($argv);
        $_SERVER['PATH_INFO'] = $_SERVER['REQUEST_URI'] = '/' . implode('/', $argv);

        $application = $this->boot(isCli: true);
        $application->send($application->run(null));
    }

    private function boot(bool $isCli): Application
    {
        Application::configureErrorHandlers();

        $context = ApplicationContext::create(
            isCli: $isCli,
            configLoader: $this->configLoader,
            basePath: $this->basePath,
            publicPath: $this->publicPath,
            temporaryPath: $this->temporaryPath,
            resourcePath: $this->resourcePath,
        );

        if ($this->builtinAutoloader !== null) {
            $context->withBuiltinAutoloader($this->builtinAutoloader);
        }

        return Application::create($context);
    }
}
