<?php

namespace SummerCraft\Core\Tests\Unit\Http;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SummerCraft\Core\Http\Uri;

class UriTest extends TestCase
{
    public function testParsesFullUri(): void
    {
        $uri = new Uri('https://user:pass@Example.COM:8443/path/to?x=1&y=2#frag');

        self::assertSame('https', $uri->getScheme());
        self::assertSame('user:pass', $uri->getUserInfo());
        self::assertSame('example.com', $uri->getHost());
        self::assertSame(8443, $uri->getPort());
        self::assertSame('user:pass@example.com:8443', $uri->getAuthority());
        self::assertSame('/path/to', $uri->getPath());
        self::assertSame('x=1&y=2', $uri->getQuery());
        self::assertSame('frag', $uri->getFragment());
    }

    public function testDefaultPortIsHidden(): void
    {
        self::assertNull((new Uri('https://example.com:443/'))->getPort());
        self::assertNull((new Uri('http://example.com:80/'))->getPort());
        self::assertSame(8080, (new Uri('http://example.com:8080/'))->getPort());
    }

    public function testToStringRoundTrip(): void
    {
        $raw = 'https://example.com/path?a=b#c';
        self::assertSame($raw, (string)new Uri($raw));
    }

    public function testWithersAreImmutable(): void
    {
        $uri = new Uri('http://example.com/');
        $modified = $uri->withScheme('https')->withHost('other.test')->withPath('/new')->withQuery('k=v');

        self::assertSame('http://example.com/', (string)$uri);
        self::assertSame('https://other.test/new?k=v', (string)$modified);
    }

    public function testInvalidPortIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new Uri('http://example.com/'))->withPort(70000);
    }

    public function testEmptyUri(): void
    {
        $uri = new Uri();
        self::assertSame('', (string)$uri);
        self::assertSame('', $uri->getAuthority());
    }
}
