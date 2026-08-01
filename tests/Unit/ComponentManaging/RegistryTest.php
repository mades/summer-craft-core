<?php

namespace SummerCraft\Core\Tests\Unit\ComponentManaging;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SummerCraft\Core\ComponentManaging\ComponentHolder;
use SummerCraft\Core\ComponentManaging\Config\Config;
use SummerCraft\Core\ComponentManaging\Registry\Registry;
use SummerCraft\Core\ComponentManaging\Registry\RegistryConfig;
use SummerCraft\Core\ComponentManaging\RequestScope;
use SummerCraft\Core\Tests\Fixture\Component\OtherRegisteredContract;
use SummerCraft\Core\Tests\Fixture\Component\PlainFixture;
use SummerCraft\Core\Tests\Fixture\Component\ScopedFixture;
use SummerCraft\Core\Tests\Fixture\Component\RegisteredContract;
use SummerCraft\Core\Tests\Fixture\Component\SharedFixture;
use SummerCraft\Core\Tests\Fixture\Component\TransientFixture;

class RegistryTest extends TestCase
{
    private RegistryConfig $config;
    private RequestScope $scope;

    protected function setUp(): void
    {
        $this->config = new RegistryConfig();
        $this->scope = new RequestScope(new ComponentHolder(new Config()));
    }

    public function testUnknownInterfaceYieldsNothing(): void
    {
        self::assertSame([], $this->collect('NoSuchInterface'));
    }

    public function testRegisteredServicesAreResolvedThroughTheContainer(): void
    {
        $this->config->add(RegisteredContract::class, SharedFixture::class);
        $this->config->add(RegisteredContract::class, PlainFixture::class);

        $resolved = $this->collect(RegisteredContract::class);

        self::assertCount(2, $resolved);
        self::assertInstanceOf(SharedFixture::class, $resolved[0]);
        self::assertInstanceOf(PlainFixture::class, $resolved[1]);
    }

    public function testExplicitOrderingWinsOverRegistrationOrder(): void
    {
        $this->config->add(RegisteredContract::class, PlainFixture::class, 20);
        $this->config->add(RegisteredContract::class, SharedFixture::class, 10);

        $resolved = $this->collect(RegisteredContract::class);

        self::assertInstanceOf(SharedFixture::class, $resolved[0]);
        self::assertInstanceOf(PlainFixture::class, $resolved[1]);
    }

    public function testDuplicateOrderingIsRefused(): void
    {
        $this->config->add(RegisteredContract::class, SharedFixture::class, 10);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Duplicate ordering');

        $this->config->add(RegisteredContract::class, PlainFixture::class, 10);
    }

    public function testAppendingAfterAnOrderedEntryTakesTheNextIndex(): void
    {
        // documented sharp edge: an appended service lands at max(index) + 1,
        // so it occupies a position a later explicit add() may want
        $this->config->add(RegisteredContract::class, SharedFixture::class, 5);
        $this->config->add(RegisteredContract::class, PlainFixture::class);

        $this->expectException(RuntimeException::class);

        $this->config->add(RegisteredContract::class, TransientFixture::class, 6);
    }

    public function testListsForDifferentInterfacesStaySeparate(): void
    {
        $this->config->add(RegisteredContract::class, SharedFixture::class);
        $this->config->add(OtherRegisteredContract::class, PlainFixture::class);

        self::assertCount(1, $this->collect(RegisteredContract::class));
        self::assertCount(1, $this->collect(OtherRegisteredContract::class));
    }

    /**
     * @return object[]
     */
    private function collect(string $interfaceName): array
    {
        $registry = new Registry($this->config, $this->scope);

        return iterator_to_array($registry->get($interfaceName), false);
    }

    /**
     * The list's promise is "everything implementing this". Nothing used to check it:
     * the interface name was a string key, so a typo, or an `implements` dropped in a
     * refactor, surfaced as a consumer receiving an object of the wrong type — far
     * from the cause. These tests are why the fixtures above implement a contract at
     * all; before, they were keyed by a name with no class behind it.
     */
    public function testRegistryRefusesAServiceThatDoesNotImplementTheContract(): void
    {
        $this->config->add(RegisteredContract::class, PlainFixture::class);
        $this->config->add(RegisteredContract::class, ScopedFixture::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('~ScopedFixture registered as .*RegisteredContract, which it does not implement~');

        $this->collect(RegisteredContract::class);
    }

    public function testAMisspelledContractNameIsRefusedToo(): void
    {
        $this->config->add('SomeInterfaceThatDoesNotExist', PlainFixture::class);

        $this->expectException(RuntimeException::class);

        $this->collect('SomeInterfaceThatDoesNotExist');
    }

    public function testAConcreteClassMayBeListedUnderItself(): void
    {
        $this->config->add(PlainFixture::class, PlainFixture::class);

        $list = $this->collect(PlainFixture::class);

        self::assertCount(1, $list);
        self::assertInstanceOf(PlainFixture::class, $list[0]);
    }
}
