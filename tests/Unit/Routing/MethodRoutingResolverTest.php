<?php

namespace SummerCraft\Core\Tests\Unit\Routing;

use PHPUnit\Framework\TestCase;
use SummerCraft\Core\Routing\Resolver\MethodRoutingResolver;
use SummerCraft\Core\Tests\Fixture\Routing\RecordingController;

class MethodRoutingResolverTest extends TestCase
{
    public function testMapsDirectlyToConfiguredMethod(): void
    {
        $resolver = MethodRoutingResolver::for(RecordingController::class, 'indexAction');

        $entryPoint = $resolver->getRoutingEntryPoint(['full-match']);

        self::assertNotNull($entryPoint);
        self::assertSame(RecordingController::class, $entryPoint->getControllerName());
        self::assertSame('indexAction', $entryPoint->getMethodName());
        self::assertSame([], $entryPoint->getMethodParams());
    }

    public function testRegexCapturesBecomeMethodParams(): void
    {
        $resolver = MethodRoutingResolver::for(RecordingController::class, 'indexAction');

        $entryPoint = $resolver->getRoutingEntryPoint(['full-match', '15', 'edit']);

        self::assertSame(['15', 'edit'], $entryPoint->getMethodParams());
    }
}
