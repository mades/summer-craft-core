<?php

namespace SummerCraft\Core\Tests\Unit\ExceptionProcessing;

use PHPUnit\Framework\TestCase;
use ReflectionClassConstant;
use RuntimeException;
use SummerCraft\Core\Exception\PhpErrorException;
use SummerCraft\Core\ExceptionProcessing\ExceptionDescription;

class ExceptionDescriptionTest extends TestCase
{
    public function testSeverityMapDoesNotReferenceRemovedEStrictConstant(): void
    {
        // E_STRICT is deprecated since PHP 8.4 and unproduced by the engine since 8.0,
        // so it must not appear as a map key. Uses the literal 2048 so the test itself
        // doesn't trigger the very deprecation it guards against.
        $constant = new ReflectionClassConstant(ExceptionDescription::class, 'SEVERITY_TO_STRING_MAP');
        $map = $constant->getValue();

        self::assertArrayNotHasKey(2048, $map);
    }

    public function testLogLevelForDeprecationIsNotice(): void
    {
        $exception = new PhpErrorException('deprecated thing');
        $exception->setSeverity(E_DEPRECATED);

        self::assertSame('notice', (new ExceptionDescription($exception))->logLevel());
    }

    public function testLogLevelForRegularExceptionIsCritical(): void
    {
        self::assertSame('critical', (new ExceptionDescription(new RuntimeException('x')))->logLevel());
    }
}
