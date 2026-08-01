<?php

namespace SummerCraft\Core\Http;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use SummerCraft\Core\ComponentManaging\LifeCycle\RequestScopeComponent;

/**
 * Mutable per-request response builder over an immutable PSR-7 response.
 * Controllers append/set into it; Application turns it into the final
 * ResponseInterface. Cookies become Set-Cookie headers.
 */
class ResponseAccumulator implements RequestScopeComponent
{
    private Response $response;

    public function __construct()
    {
        $this->response = new Response();
    }

    public function append(string $output): static
    {
        $this->response->getBody()->write($output);
        return $this;
    }

    public function setStatus(int $code, ?string $text = null): void
    {
        if ($text === null) {
            if (!isset(StatusCode::CODES[$code])) {
                throw new RuntimeException(
                    'No status text available. Please check your status code number or supply your own message text.'
                );
            }
            $text = StatusCode::CODES[$code];
        }
        $this->response = $this->response->withStatus($code, $text);
    }

    public function header(string $name, string $value, bool $replace = true): static
    {
        $this->response = $replace
            ? $this->response->withHeader($name, $value)
            : $this->response->withAddedHeader($name, $value);
        return $this;
    }

    public function redirect(string $url, bool $temporary = false): void
    {
        $this->setStatus($temporary ? StatusCode::MOVED_FOUND : StatusCode::MOVED_PERMANENTLY);
        $this->header('Location', $url);
    }

    public function denyIframe(): void
    {
        $this->header('X-Frame-Options', 'DENY');
    }

    /**
     * @param string $sameSite 'Lax', 'Strict', 'None', or '' to omit the attribute
     *                         (SameSite=None also requires $secure=true per spec)
     */
    public function cookie(
        string $name,
        ?string $value,
        int $expireTime,
        string $path = '',
        string $domain = '',
        bool $secure = false,
        bool $httpOnly = true,
        string $sameSite = 'Lax'
    ): void {
        $parts = [rawurlencode($name) . '=' . rawurlencode((string)$value)];
        if ($value === null || $value === '') {
            $expireTime = 1; // deletion semantics, as setcookie() does
        }
        if ($expireTime !== 0) {
            $parts[] = 'Expires=' . gmdate('D, d M Y H:i:s T', $expireTime);
            $parts[] = 'Max-Age=' . max(0, $expireTime - time());
        }
        if ($path !== '') {
            $parts[] = 'Path=' . $path;
        }
        if ($domain !== '') {
            $parts[] = 'Domain=' . $domain;
        }
        if ($secure) {
            $parts[] = 'Secure';
        }
        if ($httpOnly) {
            $parts[] = 'HttpOnly';
        }
        if ($sameSite !== '') {
            $parts[] = 'SameSite=' . $sameSite;
        }
        $this->response = $this->response->withAddedHeader('Set-Cookie', implode('; ', $parts));
    }

    /**
     * A PSR-7 response returned from an action replaces the accumulated one.
     */
    public function replaceWith(ResponseInterface $response): void
    {
        if (!$response instanceof Response) {
            $converted = new Response($response->getStatusCode(), (string)$response->getBody(), $response->getReasonPhrase());
            foreach ($response->getHeaders() as $name => $values) {
                foreach ($values as $value) {
                    $converted = $converted->withAddedHeader($name, $value);
                }
            }
            $response = $converted->withProtocolVersion($response->getProtocolVersion());
        }
        $this->response = $response;
    }

    public function toResponse(): Response
    {
        return $this->response;
    }

    public function content(): string
    {
        return (string)$this->response->getBody();
    }
}
