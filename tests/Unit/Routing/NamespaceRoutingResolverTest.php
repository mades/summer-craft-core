<?php

namespace SummerCraft\Core\Tests\Unit\Routing;

use PHPUnit\Framework\TestCase;
use SummerCraft\Core\Routing\Resolver\NamespaceRoutingResolver;
use SummerCraft\Core\Tests\Fixture\Routing\Namespaced\RootController;

class NamespaceRoutingResolverTest extends TestCase
{
    public function testEmptySegmentsResolveToDefaultControllerAndAction(): void
    {
        $resolver = NamespaceRoutingResolver::camelBased(RootController::class);

        $entryPoint = $resolver->getRoutingEntryPoint(['full-match', '']);

        self::assertNotNull($entryPoint);
        self::assertStringEndsWith('\\DefaultController', $entryPoint->getControllerName());
        self::assertSame('defaultAction', $entryPoint->getMethodName());
    }

    public function testRootLevelControllerAndMethodResolved(): void
    {
        $resolver = NamespaceRoutingResolver::camelBased(RootController::class);

        $entryPoint = $resolver->getRoutingEntryPoint(['full-match', 'root/index']);

        self::assertNotNull($entryPoint);
        self::assertStringEndsWith('\\RootController', $entryPoint->getControllerName());
        self::assertSame('IndexAction', $entryPoint->getMethodName());
    }

    public function testOneLevelSubdirectoryControllerResolved(): void
    {
        // dirname() has no trailing slash, so the directory-shift loop used to
        // break on the very first subdirectory segment
        $resolver = NamespaceRoutingResolver::camelBased(RootController::class);

        $entryPoint = $resolver->getRoutingEntryPoint(['full-match', 'admin/users/list']);

        self::assertNotNull($entryPoint);
        self::assertStringEndsWith('\\Admin\\UsersController', $entryPoint->getControllerName());
        self::assertSame('ListAction', $entryPoint->getMethodName());
        self::assertSame([], $entryPoint->getMethodParams());
    }

    public function testSnakeBasedResolverWorksThroughSubdirectory(): void
    {
        $resolver = NamespaceRoutingResolver::snakeBased(RootController::class);

        $entryPoint = $resolver->getRoutingEntryPoint(['full-match', 'admin/users/list']);

        self::assertNotNull($entryPoint);
        self::assertStringEndsWith('\\Admin\\Users_controller', $entryPoint->getControllerName());
        self::assertSame('List_action', $entryPoint->getMethodName());
    }

    public function testNonExistentSubdirectorySegmentFallsBackToRootController(): void
    {
        $resolver = NamespaceRoutingResolver::camelBased(RootController::class);

        // "root" is not a subdirectory, so no dir-shift happens and it's read as a controller name
        $entryPoint = $resolver->getRoutingEntryPoint(['full-match', 'nonexistentdir/index']);

        self::assertNull($entryPoint);
    }
}
