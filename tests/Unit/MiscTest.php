<?php

namespace SummerCraft\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SummerCraft\Core\BenchmarkHolder;
use SummerCraft\Core\Request\RequestIdentity;

class MiscTest extends TestCase
{
    public function testRequestIdentitiesAreUnique(): void
    {
        $first = RequestIdentity::createUnique();
        $second = RequestIdentity::createUnique();

        self::assertNotSame($first->getId(), $second->getId());
    }

    public function testBenchmarkHolderMeasuresElapsedTime(): void
    {
        $benchmark = BenchmarkHolder::getInstance();
        $benchmark->point('misc-test-start');
        usleep(1000);
        $benchmark->point('misc-test-end');

        $elapsed = $benchmark->elapsedTime('misc-test-start', 'misc-test-end');
        self::assertGreaterThan(0.0, $elapsed);
        self::assertLessThan(5.0, $elapsed);
    }

    public function testBenchmarkHolderIsSingleton(): void
    {
        self::assertSame(BenchmarkHolder::getInstance(), BenchmarkHolder::getInstance());
    }
}
