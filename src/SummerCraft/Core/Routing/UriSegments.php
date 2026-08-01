<?php

namespace SummerCraft\Core\Routing;

use Psr\Http\Message\ServerRequestInterface;
use SummerCraft\Core\ComponentManaging\LifeCycle\RequestScopeComponent;
use SummerCraft\Core\Request\RequestConfig;
use SummerCraft\Core\Routing\Exception\BadRequestException;

/**
 * URI segments of the current request: SCRIPT_NAME prefix stripped,
 * segments validated against RequestConfig::$permittedUriChars.
 * Extracted from the removed DefaultRequest.
 */
class UriSegments implements RequestScopeComponent
{
    /**
     * @var string[]
     */
    private array $segments;

    private string $segmentsUri;

    public function __construct(ServerRequestInterface $request, RequestConfig $config)
    {
        $server = $request->getServerParams();
        $path = $request->getUri()->getPath();

        $scriptName = (string)($server['SCRIPT_NAME'] ?? '');
        if ($scriptName !== '') {
            if (str_starts_with($path, $scriptName)) {
                $path = (string)substr($path, strlen($scriptName));
            } elseif (str_starts_with($path, dirname($scriptName))) {
                $path = (string)substr($path, strlen(dirname($scriptName)));
            }
        }

        $this->segments = $this->createSegments($path, $config->permittedUriChars);
        $this->segmentsUri = '/' . implode('/', $this->segments);
    }

    /**
     * @return string[]
     */
    public function segments(): array
    {
        return $this->segments;
    }

    /**
     * Normalized segments path with a leading slash, e.g. "/shop/item-1/edit".
     */
    public function uri(): string
    {
        return $this->segmentsUri;
    }

    /**
     * @return string[]
     */
    private function createSegments(string $uri, string $permittedUriChars): array
    {
        $uris = [];
        $tok = strtok($uri, '/');
        while ($tok !== false) {
            // strtok() never returns an empty token, so only '..' needs filtering
            if ($tok !== '..') {
                $uriSegment = trim($this->removeInvisibleCharacters($tok));
                if (
                    !empty($uriSegment)
                    && !empty($permittedUriChars)
                    && !preg_match('/^[' . $permittedUriChars . ']+$/iu', $uriSegment)
                ) {
                    throw new BadRequestException('The URI you submitted has disallowed characters.');
                }
                if ($uriSegment !== '') {
                    $uris[] = $uriSegment;
                }
            }
            $tok = strtok('/');
        }
        return $uris;
    }

    /**
     * Remove invisible characters — prevents sandwiching null characters
     * between ascii characters, like Java\0script.
     */
    private function removeInvisibleCharacters(string $str): string
    {
        // every control character except newline (dec 10),
        // carriage return (dec 13) and horizontal tab (dec 09)
        $nonDisplayable = ['/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S'];

        do {
            $str = preg_replace($nonDisplayable, '', $str, -1, $count);
        } while ($count);
        return $str;
    }
}
