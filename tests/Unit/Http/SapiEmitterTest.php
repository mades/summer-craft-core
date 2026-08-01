<?php

namespace SummerCraft\Core\Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use SummerCraft\Core\Http\Response;
use SummerCraft\Core\Http\SapiEmitter;
use SummerCraft\Core\Http\Stream;

class SapiEmitterTest extends TestCase
{
    private function emit(Response $response, bool $isHeadRequest = false): string
    {
        ob_start();
        SapiEmitter::emit($response, 'HTTP/1.1', $isHeadRequest);
        return ob_get_clean();
    }

    public function testEchoesBodyForOrdinaryResponse(): void
    {
        self::assertSame('hello', $this->emit(new Response(200, 'hello')));
    }

    public function testSuppressesBodyFor204(): void
    {
        self::assertSame('', $this->emit(new Response(204, 'should-not-appear')));
    }

    public function testSuppressesBodyFor304(): void
    {
        self::assertSame('', $this->emit(new Response(304, 'should-not-appear')));
    }

    public function testSuppressesBodyForHeadRequest(): void
    {
        self::assertSame('', $this->emit(new Response(200, 'should-not-appear'), isHeadRequest: true));
    }

    public function testStreamIsNotConsumedTwiceWhenBodySuppressed(): void
    {
        $response = new Response(204, '');
        $response = $response->withBody(Stream::create('irrelevant'));

        self::assertSame('', $this->emit($response));
    }
}
