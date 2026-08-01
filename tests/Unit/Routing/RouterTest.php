<?php

namespace SummerCraft\Core\Tests\Unit\Routing;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use SummerCraft\Core\BenchmarkHolder;
use SummerCraft\Core\ComponentManaging\ComponentHolder;
use SummerCraft\Core\ComponentManaging\Config\Config;
use SummerCraft\Core\ComponentManaging\RequestScope;
use SummerCraft\Core\ExceptionProcessing\ThrowableContext;
use SummerCraft\Core\Http\ResponseAccumulator;
use SummerCraft\Core\Http\ServerRequest;
use SummerCraft\Core\Routing\Exception\BadRequestException;
use SummerCraft\Core\Routing\Resolver\MethodRoutingResolver;
use SummerCraft\Core\Routing\Router;
use SummerCraft\Core\Routing\RouterConfig;
use SummerCraft\Core\Routing\RoutingEntryPoint;
use SummerCraft\Core\Routing\RoutingPattern;
use SummerCraft\Core\Tests\Fixture\Routing\NotAMiddleware;
use SummerCraft\Core\Tests\Fixture\Routing\PassMiddleware;
use SummerCraft\Core\Tests\Fixture\Routing\Psr15DecorateMiddleware;
use SummerCraft\Core\Tests\Fixture\Routing\Psr15ShortCircuitMiddleware;
use SummerCraft\Core\Tests\Fixture\Routing\Psr15TagMiddleware;
use SummerCraft\Core\Tests\Fixture\Routing\RecordingController;
use SummerCraft\Core\Tests\Fixture\Routing\StopMiddleware;

class RouterTest extends TestCase
{
    private RequestScope $scope;
    private RouterConfig $config;

    protected function setUp(): void
    {
        RecordingController::reset();
        PassMiddleware::$runs = 0;
        StopMiddleware::$runs = 0;
        Psr15TagMiddleware::$runs = 0;
        Psr15ShortCircuitMiddleware::$runs = 0;

        $this->scope = new RequestScope(new ComponentHolder(new Config()));
        $this->config = new RouterConfig();
        $this->config->entryPointForError400 =
            new RoutingEntryPoint(RecordingController::class, 'badRequestEntryAction', []);
        $this->config->entryPointForError404 =
            new RoutingEntryPoint(RecordingController::class, 'notFoundAction', []);
        $this->config->entryPointForError500 =
            new RoutingEntryPoint(RecordingController::class, 'serverErrorAction', []);
    }

    private function route(string $uriPath, string $method = 'GET'): void
    {
        $request = new ServerRequest($method, 'http://app.test' . $uriPath);
        $this->scope->set(ServerRequestInterface::class, $request);
        $router = new Router($this->scope, $this->config, BenchmarkHolder::getInstance());
        $router->route($this->scope);
    }

    private function pattern(string $uriPattern, string $action): RoutingPattern
    {
        return RoutingPattern::resolveWith(
            MethodRoutingResolver::for(RecordingController::class, $action)
        )->forUriPattern($uriPattern);
    }

    private static function calledMethods(): array
    {
        return array_column(RecordingController::$calls, 'method');
    }

    private function accumulator(): ResponseAccumulator
    {
        return $this->scope->get(ResponseAccumulator::class);
    }

    public function testMatchingPatternExecutesController(): void
    {
        $this->config->addPattern($this->pattern('/hello', 'indexAction'));

        $this->route('/hello');

        self::assertSame(['indexAction'], self::calledMethods());
    }

    /**
     * A catch-all cannot be registered last and left at that: patterns are matched in
     * the order the modules happened to load, which is their directories in
     * alphabetical order. This asks after everything else by construction.
     */
    public function testAFallbackPatternIsTriedAfterEveryOrdinaryOne(): void
    {
        $this->config->addFallbackPattern($this->pattern('/(:any)', 'notFoundAction'));
        // registered after the fallback, and still asked before it
        $this->config->addPattern($this->pattern('/hello', 'indexAction'));

        $this->route('/hello');

        self::assertSame(['indexAction'], self::calledMethods());
    }

    public function testAFallbackPatternCatchesWhatNothingElseDid(): void
    {
        $this->config->addFallbackPattern($this->pattern('/(:any)', 'indexAction'));
        $this->config->addPattern($this->pattern('/hello', 'badRequestEntryAction'));

        $this->route('/anything-else');

        self::assertSame(['indexAction'], self::calledMethods());
    }

    public function testWithoutAnyMatchTheErrorPageStillAnswers(): void
    {
        $this->config->addFallbackPattern($this->pattern('/only-this', 'indexAction'));

        $this->route('/something/deeper');

        self::assertSame(['notFoundAction'], self::calledMethods());
    }

    public function testFirstMatchingPatternWins(): void
    {
        $this->config->addPatterns([
            $this->pattern('/hello', 'indexAction'),
            $this->pattern('/(:any)', 'notFoundAction'),
        ]);

        $this->route('/hello');

        self::assertSame(['indexAction'], self::calledMethods());
    }

    public function testCapturedUriParamsArePassedToAction(): void
    {
        $this->config->addPattern($this->pattern('/greet/(:any)/(:any)', 'indexAction'));

        $this->route('/greet/john/doe');

        self::assertSame([['method' => 'indexAction', 'params' => ['john', 'doe']]], RecordingController::$calls);
    }

    public function testNoMatchFallsBackTo404EntryPoint(): void
    {
        $this->config->addPattern($this->pattern('/hello', 'indexAction'));

        $this->route('/no/such/page');

        self::assertSame(['notFoundAction'], self::calledMethods());
    }

    public function testPassingMiddlewareLetsControllerRun(): void
    {
        $this->config->addPattern(
            $this->pattern('/hello', 'indexAction')->withMiddlewares([PassMiddleware::class])
        );

        $this->route('/hello');

        self::assertSame(1, PassMiddleware::$runs);
        self::assertSame(['indexAction'], self::calledMethods());
    }

    public function testStoppingMiddlewarePreventsController(): void
    {
        $this->config->addPattern(
            $this->pattern('/hello', 'indexAction')
                ->withMiddlewares([StopMiddleware::class, PassMiddleware::class])
        );

        $this->route('/hello');

        self::assertSame(1, StopMiddleware::$runs);
        // chain stops on first false: the next middleware and the action never run
        self::assertSame(0, PassMiddleware::$runs);
        self::assertSame([], RecordingController::$calls);
    }

    public function testInvalidMiddlewareServiceFails(): void
    {
        $this->config->addPattern(
            $this->pattern('/hello', 'indexAction')->withMiddlewares([NotAMiddleware::class])
        );

        // the RuntimeException is swallowed by the generic Throwable handler → 500 entry point
        $this->route('/hello');

        self::assertSame(['serverErrorAction'], self::calledMethods());
        self::assertInstanceOf(
            \RuntimeException::class,
            $this->scope->get(ThrowableContext::class)->getThrowable()
        );
    }

    public function testPsr7ResponseFromActionReplacesAccumulated(): void
    {
        $this->config->addPattern($this->pattern('/psr', 'psrAction'));

        $this->route('/psr');

        self::assertSame(['psrAction'], self::calledMethods());
        $response = $this->accumulator()->toResponse();
        self::assertSame(201, $response->getStatusCode());
        self::assertSame('<p>psr</p>', (string)$response->getBody());
        self::assertSame('text/html; charset=UTF-8', $response->getHeaderLine('Content-Type'));
    }

    public function testPsr15MiddlewarePassesModifiedRequestDownAndSyncsScope(): void
    {
        $this->config->addPattern(
            $this->pattern('/hello', 'indexAction')->withMiddlewares([Psr15TagMiddleware::class])
        );

        $this->route('/hello');

        self::assertSame(1, Psr15TagMiddleware::$runs);
        self::assertSame(['indexAction'], self::calledMethods());
        // the tagged request replaced the scoped one for downstream services
        self::assertSame(
            'tagged',
            $this->scope->get(ServerRequestInterface::class)->getAttribute('tag')
        );
    }

    public function testPsr15ShortCircuitPreventsControllerAndBecomesResponse(): void
    {
        $this->config->addPattern(
            $this->pattern('/hello', 'indexAction')
                ->withMiddlewares([Psr15ShortCircuitMiddleware::class, PassMiddleware::class])
        );

        $this->route('/hello');

        self::assertSame(1, Psr15ShortCircuitMiddleware::$runs);
        self::assertSame(0, PassMiddleware::$runs);
        self::assertSame([], RecordingController::$calls);
        $response = $this->accumulator()->toResponse();
        self::assertSame(403, $response->getStatusCode());
        self::assertSame('<p>short-circuit</p>', (string)$response->getBody());
    }

    public function testPsr15MiddlewareDecoratesResponseAfterController(): void
    {
        $this->config->addPattern(
            $this->pattern('/psr', 'psrAction')->withMiddlewares([Psr15DecorateMiddleware::class])
        );

        $this->route('/psr');

        $response = $this->accumulator()->toResponse();
        self::assertSame(201, $response->getStatusCode());
        self::assertSame('<p>psr</p>', (string)$response->getBody());
        self::assertSame('yes', $response->getHeaderLine('X-Decorated'));
    }

    public function testLegacyAndPsr15MiddlewaresMixInOneChain(): void
    {
        $this->config->addPattern(
            $this->pattern('/hello', 'indexAction')
                ->withMiddlewares([PassMiddleware::class, Psr15TagMiddleware::class])
        );

        $this->route('/hello');

        self::assertSame(1, PassMiddleware::$runs);
        self::assertSame(1, Psr15TagMiddleware::$runs);
        self::assertSame(['indexAction'], self::calledMethods());
    }

    public function testBadRequestExceptionRoutesTo400AndStoresThrowable(): void
    {
        $this->config->addPattern($this->pattern('/hello', 'badRequestAction'));

        $this->route('/hello');

        self::assertSame(['badRequestAction', 'badRequestEntryAction'], self::calledMethods());
        self::assertInstanceOf(
            BadRequestException::class,
            $this->scope->get(ThrowableContext::class)->getThrowable()
        );
    }

    public function testGenericExceptionRoutesTo500AndStoresThrowable(): void
    {
        $this->config->addPattern($this->pattern('/hello', 'brokenAction'));

        $this->route('/hello');

        self::assertSame(['brokenAction', 'serverErrorAction'], self::calledMethods());
        $throwable = $this->scope->get(ThrowableContext::class)->getThrowable();
        self::assertSame('boom', $throwable->getMessage());
    }

    /**
     * Global middlewares must run on a route that declares none of its own —
     * no per-route opt-in needed.
     */
    public function testGlobalMiddlewareRunsWithoutRouteDeclaringAny(): void
    {
        $this->config->globalMiddlewares = [PassMiddleware::class];
        $this->config->addPattern($this->pattern('/hello', 'indexAction'));

        $this->route('/hello');

        self::assertSame(1, PassMiddleware::$runs);
        self::assertSame(['indexAction'], self::calledMethods());
    }

    public function testGlobalMiddlewareRunsBeforeRouteSpecificMiddleware(): void
    {
        $this->config->globalMiddlewares = [Psr15TagMiddleware::class];
        $this->config->addPattern(
            $this->pattern('/hello', 'indexAction')->withMiddlewares([StopMiddleware::class])
        );

        $this->route('/hello');

        // the global middleware ran (tagged the request) even though the
        // route-specific middleware stopped the chain right after
        self::assertSame(1, Psr15TagMiddleware::$runs);
        self::assertSame(1, StopMiddleware::$runs);
        self::assertSame([], RecordingController::$calls);
    }

    public function testGlobalMiddlewareCanShortCircuitBeforeController(): void
    {
        $this->config->globalMiddlewares = [Psr15ShortCircuitMiddleware::class];
        $this->config->addPattern($this->pattern('/hello', 'indexAction'));

        $this->route('/hello');

        self::assertSame(1, Psr15ShortCircuitMiddleware::$runs);
        self::assertSame([], RecordingController::$calls);
        self::assertSame(403, $this->accumulator()->toResponse()->getStatusCode());
    }
}
