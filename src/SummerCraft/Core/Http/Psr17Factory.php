<?php

namespace SummerCraft\Core\Http;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;
use RuntimeException;

/**
 * PSR-17 over this package's PSR-7 classes.
 *
 * A server that hands the application a request rather than leaving it to read
 * the superglobals — a worker runtime, a test harness, an integration that
 * embeds the framework — builds that request through these interfaces. Without
 * them the only way in is ServerRequestFactory::fromGlobals(), which is exactly
 * the door a long-lived process cannot use.
 *
 * ServerRequestFactory stays what it was: the one place that reads the
 * superglobals, for the SAPI entry point.
 */
class Psr17Factory implements
    ServerRequestFactoryInterface,
    ResponseFactoryInterface,
    StreamFactoryInterface,
    UploadedFileFactoryInterface,
    UriFactoryInterface
{
    /**
     * @param array<string, mixed> $serverParams
     */
    public function createServerRequest(string $method, $uri, array $serverParams = []): ServerRequestInterface
    {
        return new ServerRequest($method, $uri, $serverParams);
    }

    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        return new Response($code, '', $reasonPhrase);
    }

    public function createStream(string $content = ''): StreamInterface
    {
        return Stream::create($content);
    }

    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
    {
        return Stream::fromFile($filename, $mode);
    }

    /**
     * @param resource $resource
     */
    public function createStreamFromResource($resource): StreamInterface
    {
        return Stream::fromResource($resource);
    }

    public function createUploadedFile(
        StreamInterface $stream,
        ?int $size = null,
        int $error = UPLOAD_ERR_OK,
        ?string $clientFilename = null,
        ?string $clientMediaType = null
    ): UploadedFileInterface {
        return new UploadedFile(
            tempFilePath: null,
            size: $size ?? $stream->getSize(),
            error: $error,
            clientFilename: $clientFilename,
            clientMediaType: $clientMediaType,
            stream: $stream,
        );
    }

    public function createUri(string $uri = ''): UriInterface
    {
        return new Uri($uri);
    }
}
