<?php

namespace SummerCraft\Core\Tests\Unit\Routing;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SummerCraft\Core\Routing\Resolver\CliHandlerRoutingResolver;
use SummerCraft\Core\Tests\Fixture\Cli\RecordingCliHandler;

class CliHandlerRoutingResolverTest extends TestCase
{
    private const HANDLER = 'SummerCraft/Core/Tests/Fixture/Cli/RecordingCliHandler';

    public function testHandlerClassResolvesToItsHandleMethod(): void
    {
        $entryPoint = $this->resolve(self::HANDLER);

        self::assertNotNull($entryPoint);
        self::assertSame('\\' . RecordingCliHandler::class, $entryPoint->getControllerName());
        self::assertSame('handle', $entryPoint->getMethodName());
        self::assertSame([[]], $entryPoint->getMethodParams());
    }

    public function testTrailingSegmentsBecomeArguments(): void
    {
        $entryPoint = $this->resolve(self::HANDLER . '/daily/10');

        self::assertNotNull($entryPoint);
        self::assertSame('\\' . RecordingCliHandler::class, $entryPoint->getControllerName());
        self::assertSame([['daily', '10']], $entryPoint->getMethodParams());
    }

    public function testArgumentsKeepTheirOriginalCase(): void
    {
        // the class name is ucfirst()ed segment by segment; arguments are not,
        // or every argument would come back capitalised
        $entryPoint = $this->resolve(self::HANDLER . '/someValue');

        self::assertNotNull($entryPoint);
        self::assertSame([['someValue']], $entryPoint->getMethodParams());
    }

    public function testLowercasedClassSegmentsStillResolve(): void
    {
        $entryPoint = $this->resolve('summerCraft/core/tests/fixture/cli/recordingCliHandler');

        self::assertNotNull($entryPoint);
        self::assertSame('\\' . RecordingCliHandler::class, $entryPoint->getControllerName());
    }

    public function testUnknownClassDoesNotResolve(): void
    {
        self::assertNull($this->resolve('App/Module/NoSuch/Handler'));
    }

    public function testEmptyTailDoesNotResolve(): void
    {
        self::assertNull($this->resolve(''));
    }

    public function testExistingClassWithoutTheInterfaceIsRefusedLoudly(): void
    {
        // the whole point of the interface: being constructible and having a
        // handle() method is not enough to be reachable from the command line
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not implement');

        $this->resolve('SummerCraft/Core/Tests/Fixture/Cli/NotAHandler');
    }

    private function resolve(string $tail): ?\SummerCraft\Core\Routing\RoutingEntryPoint
    {
        return CliHandlerRoutingResolver::create()->getRoutingEntryPoint(['full-match', $tail]);
    }
}
