<?php

namespace SummerCraft\Core\Http;

use Psr\Http\Message\ResponseInterface;

/**
 * Emits a PSR-7 response through the PHP SAPI: status line, headers
 * (Set-Cookie always as separate lines), then the body.
 */
class SapiEmitter
{
    private const NO_BODY_STATUS_CODES = [204, 304];

    public static function emit(ResponseInterface $response, string $protocol = 'HTTP/1.1', bool $isHeadRequest = false): void
    {
        if (!headers_sent()) {
            header(
                sprintf('%s %d %s', $protocol, $response->getStatusCode(), $response->getReasonPhrase()),
                true,
                $response->getStatusCode()
            );
            foreach ($response->getHeaders() as $name => $values) {
                $isSetCookie = strtolower($name) === 'set-cookie';
                foreach ($values as $index => $value) {
                    header("$name: $value", !$isSetCookie && $index === 0);
                }
            }
        }

        // RFC 7230/7231: 204/304 and HEAD responses must not have a body, and PHP
        // does not enforce that itself — echoing one anyway corrupts the response.
        if ($isHeadRequest || in_array($response->getStatusCode(), self::NO_BODY_STATUS_CODES, true)) {
            return;
        }

        echo (string)$response->getBody();
    }
}
