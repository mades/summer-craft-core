<?php

namespace SummerCraft\Core\Tests\Unit\Http;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SummerCraft\Core\Http\Response;
use SummerCraft\Core\Http\Stream;

class ResponseTest extends TestCase
{
    public function testDefaults(): void
    {
        $response = new Response();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('OK', $response->getReasonPhrase());
        self::assertSame('1.1', $response->getProtocolVersion());
        self::assertSame('', (string)$response->getBody());
    }

    public function testWithStatusIsImmutable(): void
    {
        $response = new Response();
        $notFound = $response->withStatus(404);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(404, $notFound->getStatusCode());
        self::assertSame('Not Found', $notFound->getReasonPhrase());
    }

    public function testCustomReasonPhraseWins(): void
    {
        $response = (new Response())->withStatus(404, 'Nope');
        self::assertSame('Nope', $response->getReasonPhrase());
    }

    public function testInvalidStatusCodeRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Response(99);
    }

    public function testHeadersAreCaseInsensitive(): void
    {
        $response = (new Response())->withHeader('Content-Type', 'text/plain');

        self::assertTrue($response->hasHeader('content-type'));
        self::assertSame(['text/plain'], $response->getHeader('CONTENT-TYPE'));
        self::assertSame('text/plain', $response->getHeaderLine('content-Type'));
    }

    public function testWithAddedHeaderAppends(): void
    {
        $response = (new Response())
            ->withHeader('X-Tag', 'one')
            ->withAddedHeader('x-tag', 'two');

        self::assertSame(['one', 'two'], $response->getHeader('X-Tag'));
        self::assertSame('one, two', $response->getHeaderLine('X-Tag'));
    }

    public function testWithoutHeaderRemoves(): void
    {
        $response = (new Response())->withHeader('X-Tag', 'one');
        $cleaned = $response->withoutHeader('x-TAG');

        self::assertTrue($response->hasHeader('X-Tag'));
        self::assertFalse($cleaned->hasHeader('X-Tag'));
    }

    public function testInvalidHeaderNameRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new Response())->withHeader("Bad\r\nName", 'x');
    }

    public function testCrlfInHeaderValueRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new Response())->withHeader('Location', "http://x/\r\nSet-Cookie: pwned=1");
    }

    public function testLfInHeaderValueRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new Response())->withHeader('X-Tag', "one\nSet-Cookie: pwned=1");
    }

    public function testNulInHeaderValueRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new Response())->withHeader('X-Tag', "one\0two");
    }

    public function testHeaderValueWithSpacesAndTabsAllowed(): void
    {
        $response = (new Response())->withHeader('X-Tag', "one two\tthree");
        self::assertSame("one two\tthree", $response->getHeaderLine('X-Tag'));
    }

    public function testWithBodyReplacesStream(): void
    {
        $response = (new Response(200, 'old'))->withBody(Stream::create('new'));
        self::assertSame('new', (string)$response->getBody());
    }

    public function testHtmlFactory(): void
    {
        $response = Response::html('<h1>Hi</h1>', 201);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('text/html; charset=UTF-8', $response->getHeaderLine('Content-Type'));
        self::assertSame('<h1>Hi</h1>', (string)$response->getBody());
    }

    public function testJsonFactory(): void
    {
        $response = Response::json(['ok' => true]);

        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('{"ok":true}', (string)$response->getBody());
    }

    public function testRedirectFactory(): void
    {
        $permanent = Response::redirect('/target');
        $temporary = Response::redirect('/target', true);

        self::assertSame(301, $permanent->getStatusCode());
        self::assertSame(302, $temporary->getStatusCode());
        self::assertSame('/target', $permanent->getHeaderLine('Location'));
    }
}
