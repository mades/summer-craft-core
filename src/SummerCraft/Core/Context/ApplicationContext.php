<?php

namespace SummerCraft\Core\Context;

use SummerCraft\Core\Autoloader;

class ApplicationContext
{
    private function __construct(
        private bool $isCli,
        private string $configLoader,
        /** Root project path */
        private string $basePath,
        /** Public Html */
        private string $publicPath,
        /** User files available for all in web */
        private string $publicDynamicFilesPath,
        /** User files accessable only by application */
        private string $privateDynamicFilesPath,
        /** Generated entities: cache, logs, generated helpers */
        private string $temporaryPath,
        /** Pre-generated entities: language packages, icons, etc... */
        private string $resourcePath,
        private ?Autoloader $builtinAutoloader = null,
    ) {
    }

    public static function create(
        bool $isCli,
        string $configLoader,
        string $basePath,
        ?string $publicPath = null,
        ?string $publicDynamicFilesPath = null,
        ?string $privateDynamicFilesPath = null,
        ?string $temporaryPath = null,
        ?string $resourcePath = null,
    ): self {
        return new self(
            isCli: $isCli,
            configLoader: $configLoader,
            basePath: $basePath,
            publicPath: $publicPath ?? $basePath . 'public_html/',
            publicDynamicFilesPath: $publicDynamicFilesPath ?? $basePath  . 'storage/dynamic/public',
            privateDynamicFilesPath: $privateDynamicFilesPath ?? $basePath  . 'storage/dynamic/private',
            temporaryPath: $temporaryPath ?? $basePath . 'storage/temp/',
            resourcePath: $resourcePath ?? $basePath . 'src/resource/',
        );
    }

    public function isCLi(): bool
    {
        return $this->isCli;
    }

    public function getConfigLoader(): string
    {
        return $this->configLoader;
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    public function getPublicPath(): string
    {
        return $this->publicPath;
    }

    public function getPublicDynamicFilesPath(): string
    {
        return $this->publicDynamicFilesPath;
    }

    public function getPrivateDynamicFilesPath(): string
    {
        return $this->privateDynamicFilesPath;
    }

    public function getTemporaryPath(): string
    {
        return $this->temporaryPath;
    }

    public function getResourcePath(): string
    {
        return $this->resourcePath;
    }

    public function getBuiltinAutoloader(): ?Autoloader
    {
        return $this->builtinAutoloader;
    }

    public function withBuiltinAutoloader(Autoloader $autoloader): self
    {
        $this->builtinAutoloader = $autoloader;

        return $this;
    }
}
