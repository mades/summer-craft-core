<?php

namespace SummerCraft\Core\Tests\Unit\Routing;

use PHPUnit\Framework\TestCase;
use SummerCraft\Core\Http\ServerRequest;
use SummerCraft\Core\Request\RequestConfig;
use SummerCraft\Core\Routing\Exception\BadRequestException;
use SummerCraft\Core\Routing\UriSegments;

class UriSegmentsTest extends TestCase
{
    private function segments(string $path, array $server = []): UriSegments
    {
        $request = new ServerRequest('GET', 'http://app.test' . $path, $server);
        return new UriSegments($request, new RequestConfig());
    }

    public function testSplitsPathIntoSegments(): void
    {
        $segments = $this->segments('/shop/item-1/edit');

        self::assertSame(['shop', 'item-1', 'edit'], $segments->segments());
        self::assertSame('/shop/item-1/edit', $segments->uri());
    }

    public function testRootPathGivesEmptySegments(): void
    {
        $segments = $this->segments('/');

        self::assertSame([], $segments->segments());
        self::assertSame('/', $segments->uri());
    }

    public function testScriptNamePrefixIsStripped(): void
    {
        $segments = $this->segments('/index.php/admin/users', ['SCRIPT_NAME' => '/index.php']);

        self::assertSame(['admin', 'users'], $segments->segments());
    }

    public function testParentTraversalTokensAreDropped(): void
    {
        $segments = $this->segments('/a/../b');

        self::assertSame(['a', 'b'], $segments->segments());
    }

    public function testDisallowedCharactersAreRejected(): void
    {
        $this->expectException(BadRequestException::class);
        $this->segments('/foo/<script>');
    }

    /**
     * 'A-z' is a range-artifact bug: it also matches the ASCII punctuation between
     * 'Z' (90) and 'a' (97) — [ \ ] ^ ` (and '_', allowed anyway via the charset).
     * @dataProvider rangeArtifactCharacterProvider
     */
    public function testRangeArtifactCharactersAreRejected(string $char): void
    {
        $this->expectException(BadRequestException::class);
        $this->segments('/foo/bar' . $char . 'baz');
    }

    public static function rangeArtifactCharacterProvider(): array
    {
        return [
            '[' => ['['],
            ']' => [']'],
            '^' => ['^'],
            'backtick' => ['`'],
        ];
    }

    public function testCyrillicYoIsAllowed(): void
    {
        $segments = $this->segments('/Ёж/ёлка');

        self::assertSame(['Ёж', 'ёлка'], $segments->segments());
    }
}
