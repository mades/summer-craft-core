<?php

namespace SummerCraft\Core\Http;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use SummerCraft\Core\ComponentManaging\LifeCycle\RequestScopeComponent;
use SummerCraft\Core\Routing\Exception\BadRequestException;

/**
 * Typed access to request input over the PSR-7 request.
 * Replaces the removed native Request convenience API.
 *
 * Values are passed through {@see normalize()}, which coerces them to
 * string/array via `filter_var(..., FILTER_DEFAULT)`. That filter has no
 * effect without explicit flags — it is NOT sanitization or an XSS/injection
 * defense. Escape output for its actual context (HTML, SQL, shell, ...) at
 * the point of use; do not rely on this class for that.
 */
class RequestInput implements RequestScopeComponent
{
    public function __construct(private ServerRequestInterface $request)
    {
    }

    public function psr(): ServerRequestInterface
    {
        return $this->request;
    }

    public function queryString(string $key, string $default = ''): string
    {
        return $this->stringFrom($this->request->getQueryParams(), $key, $default, 'GET');
    }

    public function postString(string $key, string $default = ''): string
    {
        return $this->stringFrom((array)$this->request->getParsedBody(), $key, $default, 'POST');
    }

    public function queryArray(string $key): array
    {
        return $this->arrayFrom($this->request->getQueryParams(), $key, 'GET');
    }

    public function postArray(string $key): array
    {
        return $this->arrayFrom((array)$this->request->getParsedBody(), $key, 'POST');
    }

    public function allQuery(): array
    {
        return $this->normalizeAll($this->request->getQueryParams());
    }

    public function allPost(): array
    {
        return $this->normalizeAll((array)$this->request->getParsedBody());
    }

    /**
     * POST value wins over GET (historical getRequestValue semantics).
     */
    public function value(string $key, string $default = ''): string
    {
        $value = (array)$this->request->getParsedBody();
        $result = $value[$key] ?? $this->request->getQueryParams()[$key] ?? null;
        if ($result === null) {
            return $default;
        }
        return (string)$this->normalize($result);
    }

    public function cookie(string $key, string $default = ''): string
    {
        $value = $this->request->getCookieParams()[$key] ?? null;
        return $value === null ? $default : (string)$this->normalize($value);
    }

    public function header(string $name, string $default = ''): string
    {
        if (!$this->request->hasHeader($name)) {
            return $default;
        }
        return $this->request->getHeaderLine($name);
    }

    public function clientIp(): string
    {
        return (string)($this->request->getServerParams()['REMOTE_ADDR'] ?? '');
    }

    /**
     * HTTP_HOST as sent by the client; 'cli' outside a web request (historical default).
     */
    public function domain(): string
    {
        return (string)($this->request->getServerParams()['HTTP_HOST'] ?? 'cli');
    }

    /**
     * Raw request URI, e.g. "/shop/item?page=2".
     */
    public function uri(): string
    {
        return (string)($this->request->getServerParams()['REQUEST_URI'] ?? $this->request->getUri()->getPath());
    }

    public function protocol(): string
    {
        $protocol = (string)($this->request->getServerParams()['SERVER_PROTOCOL'] ?? '');
        return in_array($protocol, ['HTTP/1.0', 'HTTP/1.1', 'HTTP/2', 'HTTP/2.0', 'HTTP/3'], true)
            ? $protocol
            : 'HTTP/1.1';
    }

    /**
     * The pseudo-method the request was built with, not the SAPI: a worker
     * runtime serves HTTP from a cli process, where PHP_SAPI would say cli for
     * every visitor.
     */
    public function isCli(): bool
    {
        return $this->request->getMethod() === 'CLI';
    }

    public function isAjax(): bool
    {
        return strtolower($this->header('X-Requested-With')) === 'xmlhttprequest';
    }

    /**
     * Trusts `HTTPS`/`X-Forwarded-Proto`/`Front-End-Https` as-is — these are
     * client-controlled unless a reverse proxy in front of this app
     * overwrites them. See {@see \SummerCraft\Core\Http\ServerRequestFactory}.
     */
    public function isSecure(): bool
    {
        $server = $this->request->getServerParams();
        $httpsFlag = (string)($server['HTTPS'] ?? '');
        if ($httpsFlag !== '' && strtolower($httpsFlag) !== 'off') {
            return true;
        }
        if (($server['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
            return true;
        }
        $frontEndHttps = (string)($server['HTTP_FRONT_END_HTTPS'] ?? '');
        return $frontEndHttps !== '' && strtolower($frontEndHttps) !== 'off';
    }

    public function file(string $key): ?UploadedFile
    {
        $file = $this->request->getUploadedFiles()[$key] ?? null;
        return $file instanceof UploadedFile ? $file : null;
    }

    /**
     * @return UploadedFile[]
     */
    public function files(string $key): array
    {
        $files = $this->request->getUploadedFiles()[$key] ?? [];
        if ($files instanceof UploadedFile) {
            return [$files];
        }
        return array_values(array_filter(
            is_array($files) ? $files : [],
            fn ($file) => $file instanceof UploadedFile
        ));
    }

    private function stringFrom(array $source, string $key, string $default, string $bag): string
    {
        $value = $source[$key] ?? null;
        if ($value === null) {
            return $default;
        }
        if (!is_string($value)) {
            throw new BadRequestException("$bag key $key is not string it is: " . gettype($value));
        }
        return (string)$this->normalize($value);
    }

    private function arrayFrom(array $source, string $key, string $bag): array
    {
        $value = $source[$key] ?? null;
        if ($value === null) {
            return [];
        }
        if (!is_array($value)) {
            throw new BadRequestException("$bag key $key is not array it is: " . gettype($value));
        }
        return (array)$this->normalize($value);
    }

    private function normalizeAll(array $source): array
    {
        $result = [];
        foreach ($source as $key => $value) {
            $result[filter_var($key, FILTER_DEFAULT)] = $value !== null ? $this->normalize($value) : null;
        }
        return $result;
    }

    /**
     * Coerces the value to string/array via `filter_var(..., FILTER_DEFAULT)`.
     * NOT sanitization: FILTER_DEFAULT with no flags is a no-op on the byte
     * content — see the class docblock.
     */
    private function normalize(string|array $value): string|array
    {
        if (is_array($value)) {
            return filter_var($value, FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
        }
        return filter_var((string)$value, FILTER_DEFAULT);
    }
}
