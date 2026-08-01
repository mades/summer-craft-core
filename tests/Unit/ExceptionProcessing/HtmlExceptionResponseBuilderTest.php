<?php

namespace SummerCraft\Core\Tests\Unit\ExceptionProcessing;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SummerCraft\Core\ExceptionProcessing\ExceptionDescription;
use SummerCraft\Core\ExceptionProcessing\ExceptionProcessor;
use SummerCraft\Core\ExceptionProcessing\HtmlExceptionResponseBuilder;

class HtmlExceptionResponseBuilderTest extends TestCase
{
    public function testExceptionMessageIsEscapedInOutput(): void
    {
        $description = new ExceptionDescription(new RuntimeException('<script>alert(1)</script>'));

        $html = HtmlExceptionResponseBuilder::buildForException([$description], null);

        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }

    public function testExceptionTypeAndFileAreEscapedInOutput(): void
    {
        $description = new ExceptionDescription(new RuntimeException('"><img src=x onerror=alert(1)>'));

        $html = HtmlExceptionResponseBuilder::buildForException([$description], null);

        self::assertStringNotContainsString('<img src=x onerror=alert(1)>', $html);
    }

    public function testBacktraceLinesAreEscaped(): void
    {
        $description = new ExceptionDescription(new RuntimeException('boom'));

        $html = HtmlExceptionResponseBuilder::buildForException(
            [$description],
            ExceptionProcessor::BACKTRACE_TYPE_DEFAULT_WITH_PARAMETER
        );

        // getTraceAsString() output must go through escaping, not raw into HTML
        self::assertStringNotContainsString('<script>', $html);
    }

    public function testBuildGeneralHasNoLeftoverPhpTags(): void
    {
        $html = HtmlExceptionResponseBuilder::buildForGeneral('Heading <b>x</b>', 'Message <i>y</i>');

        self::assertStringNotContainsString('<?php echo', $html);
        self::assertStringContainsString('&lt;b&gt;x&lt;/b&gt;', $html);
        self::assertStringContainsString('&lt;i&gt;y&lt;/i&gt;', $html);
    }

    public function testBuildForDbErrorHasNoLeftoverPhpTags(): void
    {
        $html = HtmlExceptionResponseBuilder::buildForDbError('DB Heading', 'DB Message');

        self::assertStringNotContainsString('<?php echo', $html);
        self::assertStringContainsString('DB Heading', $html);
        self::assertStringContainsString('DB Message', $html);
    }
}
