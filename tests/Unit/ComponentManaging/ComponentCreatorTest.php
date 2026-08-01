<?php

namespace SummerCraft\Core\Tests\Unit\ComponentManaging;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SummerCraft\Core\ComponentManaging\ComponentHolder;
use SummerCraft\Core\ComponentManaging\Config\Config;
use SummerCraft\Core\ComponentManaging\RequestScope;
use SummerCraft\Core\Exception\ComponentException;
use SummerCraft\Core\Tests\Fixture\Component\CycleA;
use SummerCraft\Core\Tests\Fixture\Component\NeedsSharedDep;
use SummerCraft\Core\Tests\Fixture\Component\SharedFixture;
use SummerCraft\Core\Tests\Fixture\Component\SharedWithScopedDep;
use SummerCraft\Core\Tests\Fixture\Component\SharedWithSharedDep;
use SummerCraft\Core\Tests\Fixture\Component\SharedWithTransientDep;
use SummerCraft\Core\Tests\Fixture\Component\UntypedParam;
use SummerCraft\Core\Tests\Fixture\Component\WithDefaultParam;

class ComponentCreatorTest extends TestCase
{
    private ComponentHolder $holder;

    protected function setUp(): void
    {
        $this->holder = new ComponentHolder(new Config());
    }

    public function testAutowiresConstructorDependencies(): void
    {
        $component = $this->holder->get(NeedsSharedDep::class, null);

        self::assertInstanceOf(SharedFixture::class, $component->dep);
        // the injected shared dependency is the same cached instance
        self::assertSame($this->holder->get(SharedFixture::class, null), $component->dep);
    }

    public function testOptionalParametersGetDefaults(): void
    {
        $component = $this->holder->get(WithDefaultParam::class, null);

        self::assertSame(42, $component->number);
        self::assertNull($component->text);
    }

    public function testUntypedRequiredParameterFails(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Parameter type is not specified');
        $this->holder->get(UntypedParam::class, null);
    }

    public function testUnknownClassFails(): void
    {
        // without a request identity an unknown class is treated as
        // an unmarked request-scoped component, so resolve inside a scope
        $scope = new RequestScope($this->holder);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('can not be created');
        $this->holder->get('No\Such\ServiceClass', $scope->getIdentity());
    }

    public function testCircularDependencyIsDetected(): void
    {
        $this->expectException(ComponentException::class);
        $this->expectExceptionMessage('Recursive component');
        $this->holder->get(CycleA::class, null);
    }

    public function testSharedComponentMayDependOnTransient(): void
    {
        $component = $this->holder->get(SharedWithTransientDep::class, null);

        self::assertInstanceOf(SharedWithTransientDep::class, $component);
    }

    public function testSharedComponentMayNotDependOnRequestScoped(): void
    {
        $scope = new RequestScope($this->holder);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SharedScope');
        $this->expectExceptionMessage(SharedWithScopedDep::class);
        $scope->get(SharedWithScopedDep::class);
    }

    public function testSharedComponentMayDependOnShared(): void
    {
        $component = $this->holder->get(SharedWithSharedDep::class, null);

        self::assertInstanceOf(SharedFixture::class, $component->dep);
    }
}
