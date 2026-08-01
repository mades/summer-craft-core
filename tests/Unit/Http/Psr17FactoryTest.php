<?php

namespace SummerCraft\Core\Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use SummerCraft\Core\Http\Psr17Factory;

/**
 * The way in for anything that hands the application a request instead of leaving
 * it to read the superglobals: a worker runtime, an embedding host, a test harness.
 * Until this existed the only entry was ServerRequestFactory::fromGlobals(), which
 * is precisely the door a process serving many requests cannot use.
 */
class Psr17FactoryTest extends TestCase
{
    private Psr17Factory $factory;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    public function testItIsEveryFactoryARuntimeAsksFor(): void
    {
        self::assertInstanceOf(ServerRequestFactoryInterface::class, $this->factory);
        self::assertInstanceOf(ResponseFactoryInterface::class, $this->factory);
        self::assertInstanceOf(StreamFactoryInterface::class, $this->factory);
        self::assertInstanceOf(UploadedFileFactoryInterface::class, $this->factory);
        self::assertInstanceOf(UriFactoryInterface::class, $this->factory);
    }

    public function testServerRequestKeepsMethodUriAndServerParams(): void
    {
        $request = $this->factory->createServerRequest('POST', 'https://app.test/page?a=1', ['REMOTE_ADDR' => '10.0.0.1']);

        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://app.test/page?a=1', (string)$request->getUri());
        self::assertSame(['REMOTE_ADDR' => '10.0.0.1'], $request->getServerParams());
    }

    public function testServerRequestAcceptsAUriObject(): void
    {
        $request = $this->factory->createServerRequest('GET', $this->factory->createUri('https://app.test/x'));

        self::assertSame('https://app.test/x', (string)$request->getUri());
    }

    public function testResponseCarriesStatusAndReason(): void
    {
        $response = $this->factory->createResponse(404, 'Nope');

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('Nope', $response->getReasonPhrase());
    }

    public function testStreamFromString(): void
    {
        $stream = $this->factory->createStream('hello');

        self::assertSame('hello', (string)$stream);
    }

    public function testStreamFromFileAndResource(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'psr17');
        file_put_contents($file, 'from file');

        self::assertSame('from file', (string)$this->factory->createStreamFromFile($file));

        $handle = fopen($file, 'r');
        self::assertSame('from file', (string)$this->factory->createStreamFromResource($handle));

        unlink($file);
    }

    public function testUploadedFileWorksWithoutATemporaryFileOnDisk(): void
    {
        // a runtime hands over the bytes it already has; there is no $_FILES entry
        $uploaded = $this->factory->createUploadedFile(
            $this->factory->createStream('contents'),
            clientFilename: 'note.txt',
            clientMediaType: 'text/plain'
        );

        self::assertSame('contents', (string)$uploaded->getStream());
        self::assertSame(8, $uploaded->getSize(), 'the size is taken from the stream when none is given');
        self::assertSame('note.txt', $uploaded->getClientFilename());
        self::assertSame('text/plain', $uploaded->getClientMediaType());
        self::assertSame(UPLOAD_ERR_OK, $uploaded->getError());
    }

    public function testUploadedFileMovesItsStreamToDisk(): void
    {
        $uploaded = $this->factory->createUploadedFile($this->factory->createStream('payload'));
        $target = sys_get_temp_dir() . '/psr17-moved-' . uniqid() . '.txt';

        $uploaded->moveTo($target);

        self::assertSame('payload', file_get_contents($target));
        unlink($target);
    }

    public function testUri(): void
    {
        $uri = $this->factory->createUri('https://app.test:8443/path?q=1#frag');

        self::assertSame('https', $uri->getScheme());
        self::assertSame('app.test', $uri->getHost());
        self::assertSame(8443, $uri->getPort());
        self::assertSame('/path', $uri->getPath());
        self::assertSame('q=1', $uri->getQuery());
    }
}
