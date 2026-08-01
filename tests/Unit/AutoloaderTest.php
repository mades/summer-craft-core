<?php

namespace SummerCraft\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SummerCraft\Core\Autoloader;
use SummerCraft\Core\Context\Env;

class AutoloaderTest extends TestCase
{
    private string $workDir;

    protected function setUp(): void
    {
        $this->workDir = sys_get_temp_dir() . '/autoloader-test-' . uniqid();
        mkdir($this->workDir . '/cache', 0777, true);
        mkdir($this->workDir . '/base', 0777, true);
    }

    protected function tearDown(): void
    {
        // Autoloader keeps static state — reset it to a neutral config
        Autoloader::create('', '', []);
        foreach (glob($this->workDir . '/{cache,base}/*', GLOB_BRACE) ?: [] as $file) {
            unlink($file);
        }
        @rmdir($this->workDir . '/cache');
        @rmdir($this->workDir . '/base');
        @rmdir($this->workDir);
    }

    public function testFileFromClassNameUsesExactRootSegmentMapping(): void
    {
        Autoloader::create('/base/', '/cache/', ['App' => 'src/app']);
        self::assertSame(
            'src/app/Module/Thing.php',
            Autoloader::getFileFromClassName('App\Module\Thing')
        );
    }

    public function testFileFromClassNameUsesPrefixMapping(): void
    {
        Autoloader::create('/base/', '/cache/', ['Vendor\Lib' => 'lib/src']);
        self::assertSame(
            'lib/src/Thing.php',
            Autoloader::getFileFromClassName('Vendor\Lib\Thing')
        );
    }

    public function testFileFromClassNameResolvesLoadedClassViaReflection(): void
    {
        Autoloader::create('/base/', '/cache/', []);
        $file = Autoloader::getFileFromClassName(Env::class);
        self::assertStringEndsWith('src/SummerCraft/Core/Context/Env.php', $file);
        self::assertFileExists($file);
    }

    public function testFileFromClassNameFallsBackToNamespacePath(): void
    {
        Autoloader::create('/base/', '/cache/', []);
        self::assertSame(
            'No/Such/ClassX.php',
            Autoloader::getFileFromClassName('No\Such\ClassX')
        );
    }

    public function testLoadClassIncludesFile(): void
    {
        $className = 'LoadMe' . uniqid();
        file_put_contents(
            $this->workDir . '/base/' . $className . '.php',
            "<?php class {$className} {}"
        );

        $autoloader = Autoloader::create($this->workDir . '/base/', $this->workDir . '/cache/', []);

        self::assertTrue($autoloader->loadClass($className));
        self::assertTrue(class_exists($className, false));
        self::assertArrayHasKey($className, $autoloader->getLoadedClasses());
        // loadClass() no longer writes the cache file by itself — that's saveCache()'s job,
        // called explicitly (e.g. once per request), not on every single class load
        self::assertFileDoesNotExist($this->workDir . '/cache/loadedClasses.php');
    }

    public function testSaveCacheWritesLoadedClasses(): void
    {
        $className = 'LoadMe' . uniqid();
        file_put_contents(
            $this->workDir . '/base/' . $className . '.php',
            "<?php class {$className} {}"
        );
        $autoloader = Autoloader::create($this->workDir . '/base/', $this->workDir . '/cache/', []);
        $autoloader->loadClass($className);

        $autoloader->saveCache();

        self::assertFileExists($this->workDir . '/cache/loadedClasses.php');
        $cached = include $this->workDir . '/cache/loadedClasses.php';
        self::assertArrayHasKey($className, $cached);
    }

    public function testLoadClassReturnsFalseForMissingFile(): void
    {
        $autoloader = Autoloader::create($this->workDir . '/base/', $this->workDir . '/cache/', []);
        self::assertFalse($autoloader->loadClass('Missing' . uniqid()));
    }

    public function testLoadClassForMissingFileDoesNotWriteCache(): void
    {
        // a miss (typical for optional-dependency class_exists() probes) must not
        // rewrite the whole cache file every request
        $autoloader = Autoloader::create($this->workDir . '/base/', $this->workDir . '/cache/', []);

        $autoloader->loadClass('Missing' . uniqid());

        self::assertFileDoesNotExist($this->workDir . '/cache/loadedClasses.php');
    }

    public function testMissesAreNotLoggedByDefault(): void
    {
        // prepended before composer's autoloader, this one is asked about every
        // vendor class first and misses on all of them - logging that would
        // append to a file on every request forever
        $autoloader = Autoloader::create($this->workDir . '/base/', $this->workDir . '/cache/', []);

        $autoloader->loadClass('Missing' . uniqid());

        self::assertFileDoesNotExist($this->workDir . '/cache/autoloaderLog.txt');
    }

    public function testMissesAreLoggedWhenExplicitlyAskedFor(): void
    {
        $className = 'Missing' . uniqid();
        $autoloader = Autoloader::create(
            $this->workDir . '/base/',
            $this->workDir . '/cache/',
            [],
            logMisses: true
        );

        $autoloader->loadClass($className);

        self::assertStringContainsString(
            $className,
            (string)file_get_contents($this->workDir . '/cache/autoloaderLog.txt')
        );
    }

    public function testSaveCacheDoesNotLeaveTempCacheFilesBehind(): void
    {
        $className = 'LoadMe' . uniqid();
        file_put_contents(
            $this->workDir . '/base/' . $className . '.php',
            "<?php class {$className} {}"
        );
        $autoloader = Autoloader::create($this->workDir . '/base/', $this->workDir . '/cache/', []);
        $autoloader->loadClass($className);

        $autoloader->saveCache();

        self::assertSame([], glob($this->workDir . '/cache/*.tmp'));
    }
}
