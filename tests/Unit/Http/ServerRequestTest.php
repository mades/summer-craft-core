<?php

namespace SummerCraft\Core\Tests\Unit\Http;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SummerCraft\Core\Http\ServerRequest;
use SummerCraft\Core\Http\Uri;

class ServerRequestTest extends TestCase
{
    public function testBasics(): void
    {
        $request = new ServerRequest('get', 'https://example.com/path?x=1', ['SOME' => 'PARAM']);

        self::assertSame('GET', $request->getMethod());
        self::assertSame('/path?x=1', $request->getRequestTarget());
        self::assertSame('example.com', $request->getUri()->getHost());
        self::assertSame('example.com', $request->getHeaderLine('Host'));
        self::assertSame(['SOME' => 'PARAM'], $request->getServerParams());
    }

    public function testInvalidMethodRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ServerRequest('GE T', '/');
    }

    public function testWithUriUpdatesHostByDefault(): void
    {
        $request = new ServerRequest('GET', 'http://one.test/');
        $moved = $request->withUri(new Uri('http://two.test:8080/x'));

        self::assertSame('two.test:8080', $moved->getHeaderLine('Host'));
        self::assertSame('one.test', $request->getHeaderLine('Host'));
    }

    public function testWithUriPreservesHostWhenAsked(): void
    {
        $request = new ServerRequest('GET', 'http://one.test/');
        $moved = $request->withUri(new Uri('http://two.test/x'), true);

        self::assertSame('one.test', $moved->getHeaderLine('Host'));
    }

    public function testQueryParsedBodyAndAttributesAreImmutable(): void
    {
        $request = new ServerRequest('POST', '/submit');

        $withData = $request
            ->withQueryParams(['q' => '1'])
            ->withParsedBody(['field' => 'value'])
            ->withAttribute('user', 42);

        self::assertSame([], $request->getQueryParams());
        self::assertNull($request->getParsedBody());
        self::assertNull($request->getAttribute('user'));

        self::assertSame(['q' => '1'], $withData->getQueryParams());
        self::assertSame(['field' => 'value'], $withData->getParsedBody());
        self::assertSame(42, $withData->getAttribute('user'));
        self::assertSame('fallback', $withData->getAttribute('missing', 'fallback'));

        $without = $withData->withoutAttribute('user');
        self::assertNull($without->getAttribute('user'));
    }

    public function testCookieParams(): void
    {
        $request = (new ServerRequest('GET', '/'))->withCookieParams(['session' => 'abc']);
        self::assertSame(['session' => 'abc'], $request->getCookieParams());
    }

    public function testRequestTargetFallsBackToSlash(): void
    {
        $request = new ServerRequest('GET', new Uri());
        self::assertSame('/', $request->getRequestTarget());

        $custom = $request->withRequestTarget('*');
        self::assertSame('*', $custom->getRequestTarget());
    }
}
