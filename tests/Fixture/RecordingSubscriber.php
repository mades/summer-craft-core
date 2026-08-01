<?php

namespace SummerCraft\Core\Tests\Fixture;

use Psr\Http\Message\ServerRequestInterface;
use SummerCraft\Core\ComponentManaging\LifeCycle\RequestScopeComponent;
use SummerCraft\Core\BenchmarkHolder;
use SummerCraft\Core\ComponentManaging\RequestScope;
use SummerCraft\Core\EventDispatcher\Event;
use SummerCraft\Core\EventDispatcher\EventSubscriber;

class RecordingSubscriber implements EventSubscriber, RequestScopeComponent
{
    /** The container builds it, so a test that boots an application needs a way back to it. */
    public static ?self $lastInstance = null;

    /** @var Event[] */
    public array $caught = [];

    /** What the container held for the request at the moment the event fired. */
    public ?ServerRequestInterface $resolvedFromScope = null;

    /**
     * Static because the subscriber is request-scoped: each request builds its own,
     * so anything counted per request has to be collected outside them.
     *
     * @var int[] how many benchmark markers existed as each request began
     */
    private static array $markerCountsAtStart = [];

    /** @var float[] seconds since APP_START as each request began */
    private static array $elapsedAtStart = [];

    public static function forgetRequests(): void
    {
        self::$markerCountsAtStart = [];
        self::$elapsedAtStart = [];
    }

    /**
     * @return int[]
     */
    public static function markerCountsAtStart(): array
    {
        return self::$markerCountsAtStart;
    }

    /**
     * @return float[]
     */
    public static function elapsedAtStart(): array
    {
        return self::$elapsedAtStart;
    }

    public function __construct(private RequestScope $requestScope)
    {
        self::$lastInstance = $this;
    }

    public function catchEvent(Event $event): void
    {
        $this->caught[] = $event;
        $benchmark = BenchmarkHolder::getInstance();
        self::$markerCountsAtStart[] = count($benchmark->getMarkers());
        // a fresh point, because elapsedTime(APP_START) measures APP_START against
        // itself; the marker it adds is counted above, so the counts stay comparable
        self::$elapsedAtStart[] = $benchmark->elapsedTime('SubscriberSawRequest');
        // a bare scope in a unit test has no request configured, and asking would build one
        if ($this->requestScope->has(ServerRequestInterface::class)) {
            $this->resolvedFromScope = $this->requestScope->get(ServerRequestInterface::class);
        }
    }
}
