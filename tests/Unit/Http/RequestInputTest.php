<?php

namespace SummerCraft\Core\Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use SummerCraft\Core\Http\RequestInput;
use SummerCraft\Core\Http\ServerRequest;
use SummerCraft\Core\Http\Stream;
use SummerCraft\Core\Http\UploadedFile;
use SummerCraft\Core\Routing\Exception\BadRequestException;

class RequestInputTest extends TestCase
{
    private function input(
        array $query = [],
        ?array $post = null,
        array $cookies = [],
        array $server = [],
        array $headers = [],
        array $files = [],
    ): RequestInput {
        $request = new ServerRequest('POST', 'https://app.test/page', $server);
        foreach ($headers as $name => $value) {
            $request = $request->withAddedHeader($name, $value);
        }
        return new RequestInput(
            $request
                ->withQueryParams($query)
                ->withParsedBody($post)
                ->withCookieParams($cookies)
                ->withUploadedFiles($files)
        );
    }

    public function testQueryString(): void
    {
        $input = $this->input(query: ['page' => '2']);

        self::assertSame('2', $input->queryString('page'));
        self::assertSame('fallback', $input->queryString('missing', 'fallback'));
    }

    public function testQueryStringRejectsArrayValue(): void
    {
        $input = $this->input(query: ['list' => ['a']]);
        $this->expectException(BadRequestException::class);
        $input->queryString('list');
    }

    public function testPostAccessors(): void
    {
        $input = $this->input(post: ['field' => 'value', 'list' => ['a', 'b']]);

        self::assertSame('value', $input->postString('field'));
        self::assertSame(['a', 'b'], $input->postArray('list'));
        self::assertSame([], $input->postArray('missing'));
    }

    public function testPostArrayRejectsStringValue(): void
    {
        $input = $this->input(post: ['field' => 'value']);
        $this->expectException(BadRequestException::class);
        $input->postArray('field');
    }

    public function testNullParsedBodyBehavesAsEmpty(): void
    {
        $input = $this->input(post: null);
        self::assertSame('', $input->postString('anything'));
        self::assertSame([], $input->allPost());
    }

    public function testValuePrefersPostOverGet(): void
    {
        $input = $this->input(query: ['key' => 'from-get', 'other' => 'g'], post: ['key' => 'from-post']);

        self::assertSame('from-post', $input->value('key'));
        self::assertSame('g', $input->value('other'));
        self::assertSame('dflt', $input->value('none', 'dflt'));
    }

    public function testCookieHeaderIpDomainUri(): void
    {
        $input = $this->input(
            cookies: ['sid' => 'xyz'],
            server: [
                'REMOTE_ADDR' => '1.2.3.4',
                'HTTP_HOST' => 'app.test',
                'REQUEST_URI' => '/page?x=1',
                'SERVER_PROTOCOL' => 'HTTP/1.1',
            ],
            headers: ['Accept-Language' => 'pl', 'X-Requested-With' => 'XMLHttpRequest'],
        );

        self::assertSame('xyz', $input->cookie('sid'));
        self::assertSame('none', $input->cookie('missing', 'none'));
        self::assertSame('pl', $input->header('Accept-Language'));
        self::assertSame('dflt', $input->header('X-Missing', 'dflt'));
        self::assertSame('1.2.3.4', $input->clientIp());
        self::assertSame('app.test', $input->domain());
        self::assertSame('/page?x=1', $input->uri());
        self::assertSame('HTTP/1.1', $input->protocol());
        self::assertTrue($input->isAjax());
    }

    public function testProtocolAcceptsHttp2Point0AndHttp3(): void
    {
        // these used to silently collapse to HTTP/1.1
        self::assertSame('HTTP/2.0', $this->input(server: ['SERVER_PROTOCOL' => 'HTTP/2.0'])->protocol());
        self::assertSame('HTTP/3', $this->input(server: ['SERVER_PROTOCOL' => 'HTTP/3'])->protocol());
    }

    public function testProtocolFallsBackToHttp11ForUnknownValue(): void
    {
        self::assertSame('HTTP/1.1', $this->input(server: ['SERVER_PROTOCOL' => 'garbage'])->protocol());
    }

    public function testIsSecureDetectsForwardedProto(): void
    {
        self::assertTrue($this->input(server: ['HTTPS' => 'on'])->isSecure());
        self::assertTrue($this->input(server: ['HTTP_X_FORWARDED_PROTO' => 'https'])->isSecure());
        self::assertFalse($this->input(server: ['HTTPS' => 'off'])->isSecure());
        self::assertFalse($this->input()->isSecure());
    }

    public function testFiles(): void
    {
        $single = new UploadedFile(null, 4, UPLOAD_ERR_OK, 'a.txt', stream: Stream::create('data'));
        $multi = [
            new UploadedFile(null, 1, UPLOAD_ERR_OK, 'b.txt', stream: Stream::create('b')),
            new UploadedFile(null, 1, UPLOAD_ERR_OK, 'c.txt', stream: Stream::create('c')),
        ];
        $input = $this->input(files: ['avatar' => $single, 'docs' => $multi]);

        self::assertSame($single, $input->file('avatar'));
        self::assertNull($input->file('missing'));
        self::assertSame($multi, $input->files('docs'));
        self::assertSame([$single], $input->files('avatar'));
        self::assertSame([], $input->files('missing'));
    }

    public function testPsrAccessor(): void
    {
        $input = $this->input(query: ['a' => '1']);
        self::assertSame(['a' => '1'], $input->psr()->getQueryParams());
    }

    /**
     * Guards that answer "is this a console run" must not read PHP_SAPI: phpunit
     * and any worker runtime are cli processes serving ordinary HTTP requests.
     */
    public function testIsCliFollowsTheRequestMethodAndNotTheSapi(): void
    {
        self::assertSame('cli', PHP_SAPI, 'this test only means anything in a cli process');

        self::assertFalse((new RequestInput(new ServerRequest('GET', 'https://app.test/page')))->isCli());
        self::assertTrue((new RequestInput(new ServerRequest('CLI', 'https://app.test/page')))->isCli());
    }
}
