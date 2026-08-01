<?php

namespace SummerCraft\Core\Routing;

use SummerCraft\Core\ComponentManaging\LifeCycle\SharedComponent;
use SummerCraft\Core\Response\SpecificResponseHandler;

class RouterConfig implements SharedComponent
{
    public RoutingEntryPoint $entryPointForError400;
    public RoutingEntryPoint $entryPointForError404;
    public RoutingEntryPoint $entryPointForError500;

    /**
     * @var RoutingPattern[]
     */
    public array $routingPatterns = [];

    /**
     * Patterns tried only after every ordinary one has failed to match.
     *
     * A catch-all cannot simply be registered last: patterns are matched in the order
     * modules happened to be loaded, which is the alphabetical order of their
     * directories. This says "after everything else" and means it.
     *
     * @var RoutingPattern[]
     */
    public array $fallbackRoutingPatterns = [];

    /**
     * Middleware service names run before any route-specific middleware, on
     * every matched entry point (including the 400/404/500 ones) — for
     * cross-cutting concerns (e.g. CSRF) that must not depend on every route
     * remembering to opt in individually via withMiddlewares().
     * @var string[]
     */
    public array $globalMiddlewares = [];

    public function __construct()
    {
        /** @see SpecificResponseHandler::errorBadRequest() */
        $this->entryPointForError400 = new RoutingEntryPoint(SpecificResponseHandler::class, 'errorBadRequest', []);
        /** @see SpecificResponseHandler::errorNotFound() */
        $this->entryPointForError404 = new RoutingEntryPoint(SpecificResponseHandler::class, 'errorNotFound', []);
        /** @see SpecificResponseHandler::errorServerError() */
        $this->entryPointForError500 = new RoutingEntryPoint(SpecificResponseHandler::class, 'errorServerError', []);
    }

    public function addPattern(RoutingPattern $pattern): self
    {
        $this->routingPatterns[$pattern->toKeyString()] = $pattern;
        return $this;
    }

    /**
     * @param RoutingPattern[] $patterns
     * @return $this
     */
    public function addPatterns(array $patterns): self
    {
        foreach ($patterns as $pattern) {
            $this->routingPatterns[$pattern->toKeyString()] = $pattern;
        }
        return $this;
    }

    /**
     * @see $fallbackRoutingPatterns
     */
    public function addFallbackPattern(RoutingPattern $pattern): self
    {
        $this->fallbackRoutingPatterns[$pattern->toKeyString()] = $pattern;
        return $this;
    }


}