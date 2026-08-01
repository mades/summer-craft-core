<?php

namespace SummerCraft\Core\Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SummerCraft\Core\Http\Response;
use SummerCraft\Core\Http\ResponseAccumulator;

class ResponseAccumulatorTest extends TestCase
{
    public function testAppendAccumulates(): void
    {
        $accumulator = new ResponseAccumulator();
        $accumulator->append('Hello')->append(', world');

        self::assertSame('Hello, world', $accumulator->content());
        self::assertSame('Hello, world', (string)$accumulator->toResponse()->getBody());
    }

    public function testDefaultsTo200(): void
    {
        self::assertSame(200, (new ResponseAccumulator())->toResponse()->getStatusCode());
    }

    public function testSetStatus(): void
    {
        $accumulator = new ResponseAccumulator();
        $accumulator->setStatus(404);

        $response = $accumulator->toResponse();
        self::assertSame(404, $response->getStatusCode());
        self::assertSame('Not Found', $response->getReasonPhrase());
    }

    public function testSetStatusWithUnknownCodeAndNoTextFails(): void
    {
        $this->expectException(RuntimeException::class);
        (new ResponseAccumulator())->setStatus(599);
    }

    public function testHeaderReplaceAndAppend(): void
    {
        $accumulator = new ResponseAccumulator();
        $accumulator->header('X-A', '1', replace: false);
        $accumulator->header('X-A', '2', replace: false);
        self::assertSame(['1', '2'], $accumulator->toResponse()->getHeader('X-A'));

        $accumulator->header('X-A', '3');
        self::assertSame(['3'], $accumulator->toResponse()->getHeader('X-A'));
    }

    public function testRedirect(): void
    {
        $permanent = new ResponseAccumulator();
        $permanent->redirect('/target');
        self::assertSame(301, $permanent->toResponse()->getStatusCode());
        self::assertSame('/target', $permanent->toResponse()->getHeaderLine('Location'));

        $temporary = new ResponseAccumulator();
        $temporary->redirect('/target', true);
        self::assertSame(302, $temporary->toResponse()->getStatusCode());
    }

    public function testCookieBecomesSetCookieHeader(): void
    {
        $accumulator = new ResponseAccumulator();
        $accumulator->cookie('sid', 'abc xyz', time() + 3600, '/', 'app.test');
        $accumulator->cookie('tracker', 'on', 0);

        $cookies = $accumulator->toResponse()->getHeader('Set-Cookie');
        self::assertCount(2, $cookies);
        self::assertStringStartsWith('sid=abc%20xyz', $cookies[0]);
        self::assertStringContainsString('Path=/', $cookies[0]);
        self::assertStringContainsString('Domain=app.test', $cookies[0]);
        self::assertStringContainsString('Max-Age=', $cookies[0]);
        // session cookie: no expiry attributes, but security flags default on
        self::assertSame('tracker=on; HttpOnly; SameSite=Lax', $cookies[1]);
    }

    public function testNullCookieValueExpiresInThePast(): void
    {
        $accumulator = new ResponseAccumulator();
        $accumulator->cookie('sid', null, time() + 3600);

        $cookie = $accumulator->toResponse()->getHeaderLine('Set-Cookie');
        self::assertStringContainsString('Max-Age=0', $cookie);
    }

    public function testCookieDefaultsToHttpOnlyAndSameSiteLaxButNotSecure(): void
    {
        $accumulator = new ResponseAccumulator();
        $accumulator->cookie('sid', 'abc', 0);

        $cookie = $accumulator->toResponse()->getHeaderLine('Set-Cookie');
        self::assertStringContainsString('HttpOnly', $cookie);
        self::assertStringContainsString('SameSite=Lax', $cookie);
        self::assertStringNotContainsString('Secure', $cookie);
    }

    public function testCookieFlagsAreConfigurable(): void
    {
        $accumulator = new ResponseAccumulator();
        $accumulator->cookie('sid', 'abc', 0, '', '', secure: true, httpOnly: false, sameSite: 'Strict');

        $cookie = $accumulator->toResponse()->getHeaderLine('Set-Cookie');
        self::assertStringContainsString('Secure', $cookie);
        self::assertStringNotContainsString('HttpOnly', $cookie);
        self::assertStringContainsString('SameSite=Strict', $cookie);
    }

    public function testDenyIframe(): void
    {
        $accumulator = new ResponseAccumulator();
        $accumulator->denyIframe();
        self::assertSame('DENY', $accumulator->toResponse()->getHeaderLine('X-Frame-Options'));
    }

    public function testReplaceWithDiscardsAccumulatedState(): void
    {
        $accumulator = new ResponseAccumulator();
        $accumulator->append('old')->setStatus(500);

        $accumulator->replaceWith(Response::json(['ok' => true], 201));

        $response = $accumulator->toResponse();
        self::assertSame(201, $response->getStatusCode());
        self::assertSame('{"ok":true}', (string)$response->getBody());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
    }
}
