<?php

namespace SummerCraft\Core\Tests\Unit\Context;

use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use SummerCraft\Core\Context\ExtConfig;
use SummerCraft\Core\Context\Yaml;

class ExtConfigTest extends TestCase
{
    private array $envBackup;
    private $yamlDataBackup;

    private function yamlDataProperty(): ReflectionProperty
    {
        return new ReflectionProperty(Yaml::class, 'data');
    }

    protected function setUp(): void
    {
        $this->envBackup = $_ENV;
        $this->yamlDataBackup = $this->yamlDataProperty()->getValue();
    }

    protected function tearDown(): void
    {
        $_ENV = $this->envBackup;
        $this->yamlDataProperty()->setValue(null, $this->yamlDataBackup);
    }

    public function testGetStringReadsEnvKeyRegardlessOfPathCase(): void
    {
        $_ENV['MYKEY'] = 'from-env';
        $this->yamlDataProperty()->setValue(null, []);

        self::assertSame('from-env', ExtConfig::getString('myKey'));
    }

    public function testGetStringFallsBackToYamlWhenEnvMissing(): void
    {
        unset($_ENV['APP_NAME']);
        $this->yamlDataProperty()->setValue(null, ['app' => ['name' => 'from-yaml']]);

        self::assertSame('from-yaml', ExtConfig::getString('APP_NAME'));
    }

    public function testFindStringReturnsDefaultWhenMissingEverywhere(): void
    {
        unset($_ENV['NO_SUCH_KEY']);
        $this->yamlDataProperty()->setValue(null, []);

        self::assertSame('DEFAULT', ExtConfig::findString('no.such.key', 'DEFAULT'));
    }

    public function testFindStringPrefersEnvOverYaml(): void
    {
        $_ENV['APP_NAME'] = 'env-value';
        $this->yamlDataProperty()->setValue(null, ['app' => ['name' => 'yaml-value']]);

        self::assertSame('env-value', ExtConfig::findString('app_name', 'DEFAULT'));
    }
}
