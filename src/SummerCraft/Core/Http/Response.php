<?php

namespace SummerCraft\Core\Http;

use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;

/**
 * PSR-7 response. Immutable; returned from controller actions
 * or built up via ResponseAccumulator.
 */
class Response extends Message implements ResponseInterface
{
    private int $statusCode;

    public function __construct(int $statusCode = 200, string $body = '', private string $reasonPhrase = '')
    {
        $this->statusCode = self::validateStatusCode($statusCode);
        if ($body !== '') {
            $this->getBody()->write($body);
        }
    }

    public static function html(string $body, int $statusCode = 200): self
    {
        return (new self($statusCode, $body))
            ->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    /**
     * @param mixed $data
     */
    public static function json($data, int $statusCode = 200): self
    {
        return (new self($statusCode, (string)json_encode($data, JSON_UNESCAPED_UNICODE)))
            ->withHeader('Content-Type', 'application/json');
    }

    public static function redirect(string $url, bool $temporary = false): self
    {
        return (new self($temporary ? 302 : 301))
            ->withHeader('Location', $url);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function withStatus(int $code, string $reasonPhrase = ''): static
    {
        $clone = clone $this;
        $clone->statusCode = self::validateStatusCode($code);
        $clone->reasonPhrase = $reasonPhrase;
        return $clone;
    }

    public function getReasonPhrase(): string
    {
        if ($this->reasonPhrase !== '') {
            return $this->reasonPhrase;
        }
        return StatusCode::CODES[$this->statusCode] ?? '';
    }

    private static function validateStatusCode(int $code): int
    {
        if ($code < 100 || $code > 599) {
            throw new InvalidArgumentException("Invalid HTTP status code [$code]");
        }
        return $code;
    }
}
