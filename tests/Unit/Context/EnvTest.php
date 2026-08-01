<?php

namespace SummerCraft\Core\Tests\Unit\Context;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SummerCraft\Core\Context\Env;

class EnvTest extends TestCase
{
    private array $envBackup;

    protected function setUp(): void
    {
        $this->envBackup = $_ENV;
    }

    protected function tearDown(): void
    {
        $_ENV = $this->envBackup;
    }

    public function testGetStringReturnsValue(): void
    {
        $_ENV['SOME_KEY'] = 'some-value';
        self::assertSame('some-value', Env::getString('SOME_KEY'));
    }

    public function testGetStringCastsScalars(): void
    {
        $_ENV['NUMERIC_KEY'] = 123;
        self::assertSame('123', Env::getString('NUMERIC_KEY'));
    }

    public function testGetStringThrowsOnMissingKey(): void
    {
        unset($_ENV['NO_SUCH_KEY']);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('NO_SUCH_KEY');
        Env::getString('NO_SUCH_KEY');
    }

    public function testFindStringReturnsDefaultOnMissingKey(): void
    {
        unset($_ENV['NO_SUCH_KEY']);
        self::assertSame('fallback', Env::findString('NO_SUCH_KEY', 'fallback'));
    }

    public function testFindStringPrefersExistingValue(): void
    {
        $_ENV['SOME_KEY'] = 'real';
        self::assertSame('real', Env::findString('SOME_KEY', 'fallback'));
    }

    public function testIssetReflectsPresence(): void
    {
        $_ENV['PRESENT'] = '';
        unset($_ENV['ABSENT']);
        self::assertTrue(Env::isset('PRESENT'));
        self::assertFalse(Env::isset('ABSENT'));
    }

    public function testGetArrayReturnsArray(): void
    {
        $_ENV['LIST_KEY'] = ['a', 'b'];
        self::assertSame(['a', 'b'], Env::getArray('LIST_KEY'));
    }

    public function testGetArrayThrowsOnMissingKey(): void
    {
        unset($_ENV['NO_SUCH_KEY']);
        $this->expectException(RuntimeException::class);
        Env::getArray('NO_SUCH_KEY');
    }

    public function testFindArrayReturnsDefaultOnMissingKey(): void
    {
        unset($_ENV['NO_SUCH_KEY']);
        self::assertSame(['x'], Env::findArray('NO_SUCH_KEY', ['x']));
    }

    public function testLoadFromIniMergesWithoutOverridingExistingEnv(): void
    {
        $iniFile = tempnam(sys_get_temp_dir(), 'envtest');
        file_put_contents($iniFile, "FROM_INI=ini-value\nALREADY_SET=ini-value\n");
        $_ENV['ALREADY_SET'] = 'env-value';
        unset($_ENV['FROM_INI']);

        try {
            Env::loadFromIni($iniFile);
            self::assertSame('ini-value', Env::getString('FROM_INI'));
            // real environment wins over ini file
            self::assertSame('env-value', Env::getString('ALREADY_SET'));
        } finally {
            unlink($iniFile);
        }
    }

    public function testLoadFromIniThrowsOnUnparsableFile(): void
    {
        $iniFile = tempnam(sys_get_temp_dir(), 'envtest');
        // unterminated section header makes parse_ini_file() return false
        file_put_contents($iniFile, "[section\nkey = value\n");

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage($iniFile);
            @Env::loadFromIni($iniFile);
        } finally {
            unlink($iniFile);
        }
    }
}
