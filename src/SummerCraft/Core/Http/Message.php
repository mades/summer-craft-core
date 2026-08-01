<?php

namespace SummerCraft\Core\Http;

use InvalidArgumentException;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Common PSR-7 message behavior: protocol version, headers, body.
 * Headers are stored under the first-seen name, looked up case-insensitively.
 */
abstract class Message implements MessageInterface
{
    private string $protocolVersion = '1.1';

    /**
     * @var array<string, string[]> <original header name, values>
     */
    private array $headers = [];

    /**
     * @var array<string, string> <lowercase name, original name>
     */
    private array $headerNames = [];

    private ?StreamInterface $body = null;

    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    public function withProtocolVersion(string $version): static
    {
        $clone = clone $this;
        $clone->protocolVersion = $version;
        return $clone;
    }

    /**
     * @return array<string, string[]>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function hasHeader(string $name): bool
    {
        return isset($this->headerNames[strtolower($name)]);
    }

    /**
     * @return string[]
     */
    public function getHeader(string $name): array
    {
        $originalName = $this->headerNames[strtolower($name)] ?? null;
        return $originalName === null ? [] : $this->headers[$originalName];
    }

    public function getHeaderLine(string $name): string
    {
        return implode(', ', $this->getHeader($name));
    }

    public function withHeader(string $name, $value): static
    {
        $clone = clone $this;
        $clone->setHeader($name, $value, true);
        return $clone;
    }

    public function withAddedHeader(string $name, $value): static
    {
        $clone = clone $this;
        $clone->setHeader($name, $value, false);
        return $clone;
    }

    public function withoutHeader(string $name): static
    {
        $lowerName = strtolower($name);
        if (!isset($this->headerNames[$lowerName])) {
            return $this;
        }
        $clone = clone $this;
        unset($clone->headers[$clone->headerNames[$lowerName]], $clone->headerNames[$lowerName]);
        return $clone;
    }

    public function getBody(): StreamInterface
    {
        if ($this->body === null) {
            $this->body = Stream::create();
        }
        return $this->body;
    }

    public function withBody(StreamInterface $body): static
    {
        $clone = clone $this;
        $clone->body = $body;
        return $clone;
    }

    /**
     * @param mixed $value string, number, or a list of them (per the untyped PSR-7 signature)
     */
    protected function setHeader(string $name, $value, bool $replace): void
    {
        if ($name === '' || preg_match('/[^a-zA-Z0-9\'`#$%&*+.^_|~!-]/', $name)) {
            throw new InvalidArgumentException("Invalid header name [$name]");
        }
        $values = [];
        foreach (is_array($value) ? $value : [$value] as $item) {
            if (!is_string($item) && !is_numeric($item)) {
                throw new InvalidArgumentException("Invalid header value for [$name]");
            }
            $item = trim((string)$item, " \t");
            if (preg_match('/[^\x09\x20-\x7E\x80-\xFF]/', $item)) {
                throw new InvalidArgumentException("Invalid header value for [$name]: contains CR, LF, NUL or other control characters");
            }
            $values[] = $item;
        }
        if ($values === []) {
            throw new InvalidArgumentException("Header [$name] must have at least one value");
        }

        $lowerName = strtolower($name);
        if (isset($this->headerNames[$lowerName])) {
            $originalName = $this->headerNames[$lowerName];
            $this->headers[$originalName] = $replace ? $values : array_merge($this->headers[$originalName], $values);
        } else {
            $this->headerNames[$lowerName] = $name;
            $this->headers[$name] = $values;
        }
    }
}
