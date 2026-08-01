<?php

namespace SummerCraft\Core\Http;

/**
 * Builds a PSR-7 ServerRequest from PHP superglobals.
 *
 * The resulting request's host (`HTTP_HOST`/`SERVER_NAME`) and scheme are
 * taken from client-controlled headers as-is — there is no trusted
 * proxy/host allowlist here (unlike Symfony/Laravel's "trusted proxies"
 * config). Sanitizing/rewriting `Host` and `X-Forwarded-*` before they
 * reach PHP is a deploy-time responsibility of the web server/reverse
 * proxy. Do not use {@see \SummerCraft\Core\Routing\RoutingPattern::forDomain()}
 * as an access-control boundary unless that's in place.
 */
class ServerRequestFactory
{
    /**
     * @param array<string, mixed>|null $server Defaults to $_SERVER (and so on) — parameters exist for tests
     * @param array<string, mixed>|null $get
     * @param array<string, mixed>|null $post
     * @param array<string, mixed>|null $cookies
     * @param array<string, mixed>|null $files
     */
    public static function fromGlobals(
        ?array $server = null,
        ?array $get = null,
        ?array $post = null,
        ?array $cookies = null,
        ?array $files = null,
    ): ServerRequest {
        $server ??= $_SERVER;
        $post ??= $_POST;
        $cookies ??= $_COOKIE;
        $files ??= $_FILES;

        // historical behavior: a request without REQUEST_METHOD in a CLI process
        // is the pseudo-method CLI (cron/console routing relies on it)
        $method = (string)($server['REQUEST_METHOD'] ?? (PHP_SAPI === 'cli' ? 'CLI' : 'GET'));

        $scheme = (!empty($server['HTTPS']) && $server['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string)($server['HTTP_HOST'] ?? $server['SERVER_NAME'] ?? 'localhost');
        $requestUri = (string)($server['REQUEST_URI'] ?? '/');

        // parse_url() needs a host when the path/query contains "://"-like sequences.
        // A malformed REQUEST_URI makes it return false rather than an array — fall back
        // to root rather than indexing into false.
        $parts = parse_url('http://dummy' . $requestUri);
        $path = $parts !== false ? ($parts['path'] ?? '/') : '/';
        $query = $parts !== false ? ($parts['query'] ?? '') : '';

        // Nginx-style setups may pass the URI inside the query string
        if (trim($path, '/') === '' && str_starts_with($query, '/')) {
            $queryParts = explode('?', $query, 2);
            $path = $queryParts[0];
            $query = $queryParts[1] ?? '';
        }

        if ($get === null) {
            // parsed from REQUEST_URI rather than taken from $_GET, which a rewrite
            // may have filled from the wrong string. It used to be written back to
            // $_GET as well; nothing reads it there, and under a worker a write to a
            // superglobal outlives the request that made it.
            parse_str($query, $get);
        }

        $uri = new Uri($scheme . '://' . $host . $path . ($query !== '' ? '?' . $query : ''));

        $request = new ServerRequest($method, $uri, $server);

        foreach (self::extractHeaders($server) as $name => $value) {
            $request = $request->withAddedHeader($name, $value);
        }

        $body = @fopen('php://input', 'r');
        if ($body !== false) {
            $request = $request->withBody(Stream::fromResource($body));
        }

        return $request
            ->withCookieParams($cookies)
            ->withQueryParams($get)
            ->withParsedBody($post === [] ? null : $post)
            ->withUploadedFiles(self::normalizeFiles($files))
            ->withProtocolVersion(self::extractProtocolVersion($server));
    }

    /**
     * @param array<string, mixed> $server
     * @return array<string, string>
     */
    private static function extractHeaders(array $server): array
    {
        $headers = [];
        foreach ($server as $key => $value) {
            if (!is_string($value)) {
                continue;
            }
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', substr($key, 5));
                $headers[$name] = $value;
            } elseif ($key === 'CONTENT_TYPE' || $key === 'CONTENT_LENGTH') {
                $headers[str_replace('_', '-', $key)] = $value;
            }
        }
        return $headers;
    }

    /**
     * @param array<string, mixed> $server
     */
    private static function extractProtocolVersion(array $server): string
    {
        $protocol = (string)($server['SERVER_PROTOCOL'] ?? 'HTTP/1.1');
        return str_starts_with($protocol, 'HTTP/') ? substr($protocol, 5) : '1.1';
    }

    /**
     * Normalize a $_FILES-shaped array into UploadedFileInterface instances.
     * Supports plain fields and one level of array fields (name="files[]").
     *
     * @param array<string, mixed> $files
     * @return array<string, mixed>
     */
    private static function normalizeFiles(array $files): array
    {
        $normalized = [];
        foreach ($files as $field => $fileSpec) {
            if (!is_array($fileSpec) || !array_key_exists('error', $fileSpec)) {
                continue;
            }
            if (is_array($fileSpec['error'])) {
                foreach (array_keys($fileSpec['error']) as $index) {
                    $normalized[$field][$index] = self::createUploadedFile([
                        'tmp_name' => $fileSpec['tmp_name'][$index] ?? null,
                        'size' => $fileSpec['size'][$index] ?? null,
                        'error' => $fileSpec['error'][$index],
                        'name' => $fileSpec['name'][$index] ?? null,
                        'type' => $fileSpec['type'][$index] ?? null,
                    ]);
                }
            } else {
                $normalized[$field] = self::createUploadedFile($fileSpec);
            }
        }
        return $normalized;
    }

    /**
     * @param array<string, mixed> $fileSpec
     */
    private static function createUploadedFile(array $fileSpec): UploadedFile
    {
        return new UploadedFile(
            tempFilePath: isset($fileSpec['tmp_name']) && $fileSpec['tmp_name'] !== '' ? (string)$fileSpec['tmp_name'] : null,
            size: isset($fileSpec['size']) ? (int)$fileSpec['size'] : null,
            error: (int)$fileSpec['error'],
            clientFilename: isset($fileSpec['name']) ? (string)$fileSpec['name'] : null,
            clientMediaType: isset($fileSpec['type']) ? (string)$fileSpec['type'] : null,
        );
    }
}
