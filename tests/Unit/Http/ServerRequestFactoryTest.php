<?php

namespace SummerCraft\Core\Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use SummerCraft\Core\Http\ServerRequestFactory;
use SummerCraft\Core\Http\UploadedFile;

class ServerRequestFactoryTest extends TestCase
{
    private const SERVER = [
        'REQUEST_METHOD' => 'POST',
        'REQUEST_URI' => '/form/submit?source=web',
        'HTTP_HOST' => 'app.test',
        'HTTPS' => 'on',
        'SERVER_PROTOCOL' => 'HTTP/2.0',
        'HTTP_X_CUSTOM' => 'custom-value',
        'HTTP_ACCEPT' => 'text/html',
        'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
    ];

    public function testFromGlobalsBuildsFullRequest(): void
    {
        $request = ServerRequestFactory::fromGlobals(
            server: self::SERVER,
            get: ['source' => 'web'],
            post: ['field' => 'value'],
            cookies: ['session' => 'abc'],
            files: [],
        );

        self::assertSame('POST', $request->getMethod());
        self::assertSame('https', $request->getUri()->getScheme());
        self::assertSame('app.test', $request->getUri()->getHost());
        self::assertSame('/form/submit', $request->getUri()->getPath());
        self::assertSame('source=web', $request->getUri()->getQuery());
        self::assertSame('2.0', $request->getProtocolVersion());

        self::assertSame('custom-value', $request->getHeaderLine('X-Custom'));
        self::assertSame('text/html', $request->getHeaderLine('Accept'));
        self::assertSame('application/x-www-form-urlencoded', $request->getHeaderLine('Content-Type'));

        self::assertSame(['source' => 'web'], $request->getQueryParams());
        self::assertSame(['field' => 'value'], $request->getParsedBody());
        self::assertSame(['session' => 'abc'], $request->getCookieParams());
        self::assertSame(self::SERVER, $request->getServerParams());
    }

    public function testEmptyPostBecomesNullParsedBody(): void
    {
        $request = ServerRequestFactory::fromGlobals(server: self::SERVER, get: [], post: [], cookies: [], files: []);
        self::assertNull($request->getParsedBody());
    }

    public function testMalformedRequestUriFallsBackToRootPath(): void
    {
        // a REQUEST_URI that makes parse_url('http://dummy' . $requestUri)
        // return false must not crash
        $request = ServerRequestFactory::fromGlobals(
            server: ['REQUEST_METHOD' => 'GET', 'HTTP_HOST' => 'app.test', 'REQUEST_URI' => ':-1/foo'],
            get: [], post: [], cookies: [], files: [],
        );

        self::assertSame('/', $request->getUri()->getPath());
    }

    public function testHttpDefaultsWithoutHttps(): void
    {
        $request = ServerRequestFactory::fromGlobals(
            server: ['REQUEST_METHOD' => 'GET', 'HTTP_HOST' => 'plain.test', 'REQUEST_URI' => '/'],
            get: [], post: [], cookies: [], files: [],
        );
        self::assertSame('http', $request->getUri()->getScheme());
        self::assertSame('1.1', $request->getProtocolVersion());
    }

    public function testNormalizesSingleFile(): void
    {
        $request = ServerRequestFactory::fromGlobals(
            server: self::SERVER, get: [], post: [], cookies: [],
            files: [
                'avatar' => [
                    'tmp_name' => '/tmp/php123',
                    'size' => 100,
                    'error' => UPLOAD_ERR_OK,
                    'name' => 'me.png',
                    'type' => 'image/png',
                ],
            ],
        );

        $files = $request->getUploadedFiles();
        self::assertInstanceOf(UploadedFile::class, $files['avatar']);
        self::assertSame('me.png', $files['avatar']->getClientFilename());
        self::assertSame(100, $files['avatar']->getSize());
        self::assertSame(UPLOAD_ERR_OK, $files['avatar']->getError());
    }

    public function testNormalizesFileArray(): void
    {
        $request = ServerRequestFactory::fromGlobals(
            server: self::SERVER, get: [], post: [], cookies: [],
            files: [
                'docs' => [
                    'tmp_name' => ['/tmp/a', '/tmp/b'],
                    'size' => [1, 2],
                    'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE],
                    'name' => ['a.txt', 'b.txt'],
                    'type' => ['text/plain', 'text/plain'],
                ],
            ],
        );

        $files = $request->getUploadedFiles();
        self::assertCount(2, $files['docs']);
        self::assertSame('a.txt', $files['docs'][0]->getClientFilename());
        self::assertSame(UPLOAD_ERR_NO_FILE, $files['docs'][1]->getError());
    }
}
