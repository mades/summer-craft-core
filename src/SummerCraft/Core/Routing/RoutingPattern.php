<?php

namespace SummerCraft\Core\Routing;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use SummerCraft\Core\Routing\Resolver\CliHandlerRoutingResolver;
use SummerCraft\Core\Routing\Resolver\ControllerRoutingResolver;
use SummerCraft\Core\Routing\Resolver\MethodRoutingResolver;
use SummerCraft\Core\Routing\Resolver\RoutingResolver;

class RoutingPattern
{
    private const URI_PATTERN_REG_FROM = [':any', ':num', ':all'];
    private const URI_PATTERN_REG_TO = ['[^/]+', '[0-9]+', '.+'];

    /**
     * @var string[] Uppercase HTTP method names, e.g. "GET"
     */
    private ?array $allowMethods = null;

    /**
     * @var string[]
     */
    private ?array $domains = null;

    /**
     * @var string[]
     */
    private array $uriPatterns = [];

    /**
     * @var string[]
     */
    private array $middlewareServiceNames = [];

    public function __construct(
        private RoutingResolver $routingEntryPoint,
    ) {
    }

    public static function resolveWith(RoutingResolver $routingResolver): self
    {
        return new self($routingResolver);
    }

    /**
     * Shorthand for {@see resolveWith()} + {@see ControllerRoutingResolver::camelBased()}.
     */
    public static function controller(string $className, int $variablesCount = 0): self
    {
        return new self(ControllerRoutingResolver::camelBased($className, $variablesCount));
    }

    /**
     * Shorthand for {@see resolveWith()} + {@see ControllerRoutingResolver::snakeBased()}.
     */
    public static function controllerSnake(string $className, int $variablesCount = 0): self
    {
        return new self(ControllerRoutingResolver::snakeBased($className, $variablesCount));
    }

    /**
     * Shorthand for {@see resolveWith()} + {@see MethodRoutingResolver::for()}.
     */
    public static function method(string $className, string $methodName): self
    {
        return new self(MethodRoutingResolver::for($className, $methodName));
    }

    /**
     * Shorthand for {@see resolveWith()} + {@see CliHandlerRoutingResolver::create()}.
     * Pair it with ->forMethod('CLI') — see the resolver for why.
     */
    public static function cliHandler(): self
    {
        return new self(CliHandlerRoutingResolver::create());
    }

    public function forMethod(string $allowMethod): RoutingPattern
    {
        $this->allowMethods[] = strtoupper($allowMethod);
        return $this;
    }

    /**
     * Restricts this pattern to the given domain, matched against the
     * client-sent `Host` header (see {@see \SummerCraft\Core\Http\ServerRequestFactory}).
     * There is no trusted-host allowlist behind this — it's a routing
     * convenience, not an access-control boundary. A forged `Host` header
     * can satisfy this match unless a reverse proxy already validates it.
     */
    public function forDomain(?string $domain): RoutingPattern
    {
        if ($domain === null) {
            return $this;
        }
        $this->domains[] = $domain;
        return $this;
    }

    /**
     * @see forDomain() for the trust caveat — same unvalidated Host header.
     */
    public function forDomains(array $domains): RoutingPattern
    {
        $this->domains = array_merge($this->domains ?? [], $domains);
        return $this;
    }

    public function forUriPattern(string $uriPattern): RoutingPattern
    {
        $this->uriPatterns[] = $uriPattern;
        return $this;
    }

    public function forUriPatterns(array $uriPatterns): RoutingPattern
    {
        $this->uriPatterns = $uriPatterns;
        return $this;
    }

    /**
     * @param string[] $middlewareServiceNames
     * @return $this
     */
    public function withMiddlewares(array $middlewareServiceNames): RoutingPattern
    {
        $this->middlewareServiceNames = $middlewareServiceNames;
        return $this;
    }

    public function check(
        ServerRequestInterface $request,
        string $segmentsUri,
        ?LoggerInterface $debugLogger = null
    ): ?RoutingEntryPoint {
        if ($debugLogger) {
            $debugLogger->debug('RoutingPattern::check ' . json_encode([
                'pattern-domains' => $this->domains,
                'pattern-uriPatterns' => $this->uriPatterns,
                'pattern-allowMethods' => $this->allowMethods,
            ]));
        }

        $requestMethod = strtoupper($request->getMethod());
        if ($this->allowMethods !== null && !in_array($requestMethod, $this->allowMethods, true)) {
            if ($debugLogger) {
                $debugLogger->debug('Not allowed by Method ' . json_encode([
                    'pattern-allowMethods' => $this->allowMethods,
                    'requestMethod' => $requestMethod,
                ]));
            }
            return null;
        }

        $requestDomain = $request->getUri()->getHost();
        if ($this->domains !== null && !in_array($requestDomain, $this->domains, true)) {
            if ($debugLogger) {
                $debugLogger->debug('Not allowed by Domain ' . json_encode([
                    'pattern-domains' =>  $this->domains,
                    'requestDomain' => $requestDomain,
                ]));
            }
            return null;
        }

        foreach ($this->uriPatterns as $uriPattern) {
            $uriRegPattern = str_replace(self::URI_PATTERN_REG_FROM, self::URI_PATTERN_REG_TO, $uriPattern);

            $matches = [];
            if (!preg_match('#^'.$uriRegPattern.'$#', $segmentsUri, $matches)) {
                if ($debugLogger) {
                    $debugLogger->debug('Not allowed by Uri Pattern ' . json_encode([
                        'pattern-uri' => '#^'.$uriRegPattern.'$#',
                        'requestSegmentsUri' => $segmentsUri,
                    ]));
                }
                continue;
            }

            if ($debugLogger) {
                $debugLogger->debug('Matched to pattern ' . json_encode([
                    'pattern-domains' => $this->domains,
                    'pattern-uriPatterns' => $this->uriPatterns,
                    'pattern-allowMethods' => $this->allowMethods,
                    'requestDomain' => $requestDomain,
                    'requestSegmentsUri' => $segmentsUri,
                    'requestMethod' => $requestMethod,
                ]));
            }

            $result = $this->routingEntryPoint->getRoutingEntryPoint($matches, $debugLogger);
            if ($result) {
                $result->setMiddlewares($this->middlewareServiceNames);
                return $result;
            }
        }

        return null;
    }

    public function toKeyString(): string
    {
        return
            ($this->allowMethods ? implode(',', $this->allowMethods) : '')
            . '|'
            . ($this->domains ? implode(',', $this->domains) : '')
            . '|'
            . implode(',', $this->uriPatterns);
    }
}
