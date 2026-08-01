<?php

namespace SummerCraft\Core\Tests\Unit\Context;

use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use SummerCraft\Core\Context\Yaml;

class YamlTest extends TestCase
{
    private $dataBackup;

    private function dataProperty(): ReflectionProperty
    {
        return new ReflectionProperty(Yaml::class, 'data');
    }

    protected function setUp(): void
    {
        $this->dataBackup = $this->dataProperty()->getValue();
    }

    protected function tearDown(): void
    {
        $this->dataProperty()->setValue(null, $this->dataBackup);
    }

    private function setYamlData(array $data): void
    {
        $this->dataProperty()->setValue(null, $data);
    }

    public function testGetStringReturnsValue(): void
    {
        $this->setYamlData(['app' => ['name' => 'summer-craft']]);
        self::assertSame('summer-craft', Yaml::getString('app.name'));
    }

    public function testGetStringThrowsOnMissingPath(): void
    {
        $this->setYamlData([]);
        $this->expectException(RuntimeException::class);
        Yaml::getString('no.such.path');
    }

    public function testFindStringReturnsDefaultOnMissingPath(): void
    {
        $this->setYamlData([]);
        self::assertSame('DEFAULT', Yaml::findString('no.such.path', 'DEFAULT'));
    }

    public function testFindStringPrefersExistingValue(): void
    {
        $this->setYamlData(['app' => ['name' => 'real-value']]);
        self::assertSame('real-value', Yaml::findString('app.name', 'DEFAULT'));
    }

    public function testFindStringThrowsWhenValueIsNotAString(): void
    {
        $this->setYamlData(['app' => ['list' => ['a', 'b']]]);
        $this->expectException(RuntimeException::class);
        Yaml::findString('app.list', 'DEFAULT');
    }
}
