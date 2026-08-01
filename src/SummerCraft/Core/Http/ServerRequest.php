<?php

namespace SummerCraft\Core\Http;

use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

/**
 * PSR-7 server request. Immutable.
 */
class ServerRequest extends Message implements ServerRequestInterface
{
    private string $method;

    private UriInterface $uri;

    private ?string $requestTarget = null;

    /** @var array<string, mixed> */
    private array $cookieParams = [];

    /** @var array<string, mixed> */
    private array $queryParams = [];

    /** @var array<string, mixed> */
    private array $uploadedFiles = [];

    /** @var array<mixed>|object|null */
    private $parsedBody = null;

    /** @var array<string, mixed> */
    private array $attributes = [];

    /**
     * @param array<string, mixed> $serverParams
     */
    public function __construct(
        string $method,
        UriInterface|string $uri,
        private array $serverParams = [],
    ) {
        $this->method = self::validateMethod($method);
        $this->uri = is_string($uri) ? new Uri($uri) : $uri;
        if ($this->uri->getHost() !== '') {
            $this->setHeader('Host', $this->uri->getHost(), true);
        }
    }

    public function getRequestTarget(): string
    {
        if ($this->requestTarget !== null) {
            return $this->requestTarget;
        }
        $target = $this->uri->getPath();
        if ($target === '') {
            $target = '/';
        }
        if ($this->uri->getQuery() !== '') {
            $target .= '?' . $this->uri->getQuery();
        }
        return $target;
    }

    public function withRequestTarget(string $requestTarget): static
    {
        $clone = clone $this;
        $clone->requestTarget = $requestTarget;
        return $clone;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function withMethod(string $method): static
    {
        $clone = clone $this;
        $clone->method = self::validateMethod($method);
        return $clone;
    }

    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    public function withUri(UriInterface $uri, bool $preserveHost = false): static
    {
        $clone = clone $this;
        $clone->uri = $uri;
        $shouldUpdateHost = !$preserveHost || !$this->hasHeader('Host');
        if ($shouldUpdateHost && $uri->getHost() !== '') {
            $host = $uri->getHost() . ($uri->getPort() !== null ? ':' . $uri->getPort() : '');
            $clone->setHeader('Host', $host, true);
        }
        return $clone;
    }

    /**
     * @return array<string, mixed>
     */
    public function getServerParams(): array
    {
        return $this->serverParams;
    }

    /**
     * @return array<string, mixed>
     */
    public function getCookieParams(): array
    {
        return $this->cookieParams;
    }

    public function withCookieParams(array $cookies): static
    {
        $clone = clone $this;
        $clone->cookieParams = $cookies;
        return $clone;
    }

    /**
     * @return array<string, mixed>
     */
    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    public function withQueryParams(array $query): static
    {
        $clone = clone $this;
        $clone->queryParams = $query;
        return $clone;
    }

    /**
     * @return array<string, mixed>
     */
    public function getUploadedFiles(): array
    {
        return $this->uploadedFiles;
    }

    public function withUploadedFiles(array $uploadedFiles): static
    {
        $clone = clone $this;
        $clone->uploadedFiles = $uploadedFiles;
        return $clone;
    }

    public function getParsedBody()
    {
        return $this->parsedBody;
    }

    public function withParsedBody($data): static
    {
        $clone = clone $this;
        $clone->parsedBody = $data;
        return $clone;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getAttribute(string $name, $default = null)
    {
        return $this->attributes[$name] ?? $default;
    }

    public function withAttribute(string $name, $value): static
    {
        $clone = clone $this;
        $clone->attributes[$name] = $value;
        return $clone;
    }

    public function withoutAttribute(string $name): static
    {
        $clone = clone $this;
        unset($clone->attributes[$name]);
        return $clone;
    }

    private static function validateMethod(string $method): string
    {
        if ($method === '' || preg_match('/[^a-zA-Z]/', $method)) {
            throw new InvalidArgumentException("Invalid HTTP method [$method]");
        }
        return strtoupper($method);
    }
}
