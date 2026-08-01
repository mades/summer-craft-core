<?php

namespace SummerCraft\Core\Tests\Unit\Routing;

use PHPUnit\Framework\TestCase;
use SummerCraft\Core\Routing\Resolver\ControllerRoutingResolver;
use SummerCraft\Core\Tests\Fixture\Routing\CamelController;
use SummerCraft\Core\Tests\Fixture\Routing\SnakeController;

class ControllerRoutingResolverTest extends TestCase
{
    public function testEmptySegmentsResolveToDefaultAction(): void
    {
        $resolver = ControllerRoutingResolver::camelBased(CamelController::class);

        $entryPoint = $resolver->getRoutingEntryPoint(['full-match', '']);

        self::assertNotNull($entryPoint);
        self::assertSame('defaultAction', $entryPoint->getMethodName());
        self::assertSame([], $entryPoint->getMethodParams());
    }

    public function testFirstSegmentBecomesActionAndRestBecomeParams(): void
    {
        $resolver = ControllerRoutingResolver::camelBased(CamelController::class);

        $entryPoint = $resolver->getRoutingEntryPoint(['full-match', 'hello/one/two']);

        self::assertNotNull($entryPoint);
        self::assertSame('helloAction', $entryPoint->getMethodName());
        self::assertSame(['one', 'two'], $entryPoint->getMethodParams());
    }

    public function testLeadingSlashInSegmentsIsIgnored(): void
    {
        $resolver = ControllerRoutingResolver::camelBased(CamelController::class);

        $entryPoint = $resolver->getRoutingEntryPoint(['full-match', '/hello']);

        self::assertNotNull($entryPoint);
        self::assertSame('helloAction', $entryPoint->getMethodName());
    }

    public function testHyphenInUrlMapsToHyphenMarkerMethod(): void
    {
        $resolver = ControllerRoutingResolver::camelBased(CamelController::class);

        $entryPoint = $resolver->getRoutingEntryPoint(['full-match', 'some-part']);

        self::assertNotNull($entryPoint);
        self::assertSame('some__Hyphen__partAction', $entryPoint->getMethodName());
    }

    public function testLiteralMarkerInUrlIsRejected(): void
    {
        $resolver = ControllerRoutingResolver::camelBased(CamelController::class);

        // a client must not be able to spoof the internal marker directly
        self::assertNull($resolver->getRoutingEntryPoint(['full-match', 'some__Hyphen__part']));
    }

    public function testUnknownMethodReturnsNull(): void
    {
        $resolver = ControllerRoutingResolver::camelBased(CamelController::class);

        self::assertNull($resolver->getRoutingEntryPoint(['full-match', 'nonexistent']));
    }

    public function testUnknownControllerClassReturnsNull(): void
    {
        $resolver = ControllerRoutingResolver::camelBased('No\Such\ControllerClass');

        self::assertNull($resolver->getRoutingEntryPoint(['full-match', 'hello']));
    }

    public function testVariablesFromUriPatternArePrependedToParams(): void
    {
        $resolver = ControllerRoutingResolver::camelBased(CamelController::class, 1);

        // match data: [full, captured-variable, remaining-segments]
        $entryPoint = $resolver->getRoutingEntryPoint(['full-match', 'captured', 'hello/tail']);

        self::assertNotNull($entryPoint);
        self::assertSame('helloAction', $entryPoint->getMethodName());
        self::assertSame(['captured', 'tail'], $entryPoint->getMethodParams());
    }

    public function testSnakeBasedResolverUsesSnakePostfix(): void
    {
        $resolver = ControllerRoutingResolver::snakeBased(SnakeController::class);

        $default = $resolver->getRoutingEntryPoint(['full-match', '']);
        self::assertNotNull($default);
        self::assertSame('default_action', $default->getMethodName());

        $named = $resolver->getRoutingEntryPoint(['full-match', 'hello']);
        self::assertNotNull($named);
        self::assertSame('hello_action', $named->getMethodName());
    }
}
