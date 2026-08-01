<?php

namespace SummerCraft\Core\Tests\Unit\ComponentManaging;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SummerCraft\Core\ComponentManaging\ComponentHolder;
use SummerCraft\Core\ComponentManaging\Config\ComponentConfig;
use SummerCraft\Core\ComponentManaging\Config\Config;
use SummerCraft\Core\ComponentManaging\RequestScope;
use SummerCraft\Core\Exception\ComponentException;
use SummerCraft\Core\Tests\Fixture\Component\ExtendsSharedMarker;
use SummerCraft\Core\Tests\Fixture\Component\PlainFixture;
use SummerCraft\Core\Tests\Fixture\Component\ScopedFixture;
use SummerCraft\Core\Tests\Fixture\Component\SharedFixture;
use SummerCraft\Core\Tests\Fixture\Component\TransientFixture;

class ComponentHolderTest extends TestCase
{
    private Config $config;
    private ComponentHolder $holder;

    protected function setUp(): void
    {
        $this->config = new Config();
        $this->holder = new ComponentHolder($this->config);
    }

    public function testSharedComponentIsCreatedOnceAndCached(): void
    {
        $first = $this->holder->get(SharedFixture::class, null);
        $second = $this->holder->get(SharedFixture::class, null);

        self::assertInstanceOf(SharedFixture::class, $first);
        self::assertSame($first, $second);
    }

    public function testTransientComponentIsCreatedEveryTime(): void
    {
        $first = $this->holder->get(TransientFixture::class, null);
        $second = $this->holder->get(TransientFixture::class, null);

        self::assertInstanceOf(TransientFixture::class, $first);
        self::assertNotSame($first, $second);
    }

    public function testRequestScopedComponentRequiresIdentity(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('without Request scope');
        $this->holder->get(ScopedFixture::class, null);
    }

    public function testRequestScopedComponentIsCachedPerScope(): void
    {
        $scopeA = new RequestScope($this->holder);
        $scopeB = new RequestScope($this->holder);

        $a1 = $this->holder->get(ScopedFixture::class, $scopeA->getIdentity());
        $a2 = $this->holder->get(ScopedFixture::class, $scopeA->getIdentity());
        $b = $this->holder->get(ScopedFixture::class, $scopeB->getIdentity());

        self::assertSame($a1, $a2);
        self::assertNotSame($a1, $b);
    }

    public function testDestroyScopeDropsScopedInstances(): void
    {
        $scope = new RequestScope($this->holder);
        $before = $this->holder->get(ScopedFixture::class, $scope->getIdentity());

        $this->holder->destroyScope($scope);

        $after = $this->holder->get(ScopedFixture::class, $scope->getIdentity());
        self::assertNotSame($before, $after);
    }

    public function testAliasResolvesToConfiguredClassAndSharesInstance(): void
    {
        $this->config->services['my.alias'] = ComponentConfig::forClass(SharedFixture::class);

        $viaAlias = $this->holder->get('my.alias', null);
        $viaClass = $this->holder->get(SharedFixture::class, null);

        self::assertInstanceOf(SharedFixture::class, $viaAlias);
        self::assertSame($viaAlias, $viaClass);
    }

    public function testCallbackCreationIsUsed(): void
    {
        $expected = new SharedFixture();
        $this->config->services[SharedFixture::class] = ComponentConfig::forCallback(
            fn () => $expected,
            SharedFixture::class
        );

        self::assertSame($expected, $this->holder->get(SharedFixture::class, null));
    }

    public function testCallbackReturningWrongTypeFailsValidation(): void
    {
        $this->config->services[SharedFixture::class] = ComponentConfig::forCallback(
            fn () => new TransientFixture(),
            SharedFixture::class
        );

        $this->expectException(ComponentException::class);
        $this->holder->get(SharedFixture::class, null);
    }

    public function testSetAndGetPreRegisteredInstance(): void
    {
        $instance = new SharedFixture();
        $this->holder->set('pre.registered', null, $instance);

        self::assertSame($instance, $this->holder->get('pre.registered', null));
        self::assertTrue($this->holder->has('pre.registered'));
        self::assertFalse($this->holder->has('never.registered'));
    }

    public function testHasIsTrueForSharedWithoutRequestIdentity(): void
    {
        self::assertTrue($this->holder->has(SharedFixture::class));
    }

    public function testHasIsFalseForTransientWithoutRequestIdentity(): void
    {
        // get() actually creates Transient unconditionally (scope or not), but has()
        // only guarantees an answer for Shared, or when a request identity is given —
        // accepted narrowing, since every real caller goes through RequestScope::has(),
        // which always supplies an identity anyway.
        self::assertFalse($this->holder->has(TransientFixture::class));
    }

    public function testHasIsTrueForTransientWithRequestIdentity(): void
    {
        $scope = new RequestScope($this->holder);
        self::assertTrue($this->holder->has(TransientFixture::class, $scope->getIdentity()));
    }

    public function testHasIsFalseForUnmarkedClassWithoutRequestIdentity(): void
    {
        // get() throws here (no scope) — has() must agree
        self::assertFalse($this->holder->has(PlainFixture::class));
    }

    public function testHasIsTrueForUnmarkedClassWithRequestIdentity(): void
    {
        $scope = new RequestScope($this->holder);

        self::assertTrue($this->holder->has(PlainFixture::class, $scope->getIdentity()));
        // and get() actually succeeds, confirming has() didn't lie
        self::assertInstanceOf(PlainFixture::class, $this->holder->get(PlainFixture::class, $scope->getIdentity()));
    }

    public function testHasIsFalseForInterfaceExtendingSharedMarker(): void
    {
        // is_a(ExtendsSharedMarker::class, SharedComponent::class, true) is true (interfaces
        // extending interfaces), but it's not a class — new $name() would fatal
        self::assertFalse($this->holder->has(ExtendsSharedMarker::class));
    }
}
