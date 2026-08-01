<?php

namespace SummerCraft\Core\Tests\Unit\Routing;

use PHPUnit\Framework\TestCase;
use SummerCraft\Core\Http\ServerRequest;
use SummerCraft\Core\Routing\Resolver\MethodRoutingResolver;
use SummerCraft\Core\Routing\RoutingPattern;
use SummerCraft\Core\Tests\Fixture\Routing\CamelController;
use SummerCraft\Core\Tests\Fixture\Routing\RecordingController;
use SummerCraft\Core\Tests\Fixture\Routing\SnakeController;

class RoutingPatternTest extends TestCase
{
    private function pattern(): RoutingPattern
    {
        return RoutingPattern::resolveWith(
            MethodRoutingResolver::for(RecordingController::class, 'indexAction')
        );
    }

    private function request(string $method = 'GET', string $host = 'example.test'): ServerRequest
    {
        return new ServerRequest($method, "http://$host/");
    }

    public function testMatchesExactUriPattern(): void
    {
        $entryPoint = $this->pattern()
            ->forUriPattern('/hello')
            ->check($this->request(), '/hello');

        self::assertNotNull($entryPoint);
        self::assertSame(RecordingController::class, $entryPoint->getControllerName());
        self::assertSame('indexAction', $entryPoint->getMethodName());
    }

    public function testDoesNotMatchDifferentUri(): void
    {
        $entryPoint = $this->pattern()
            ->forUriPattern('/hello')
            ->check($this->request(), '/goodbye');

        self::assertNull($entryPoint);
    }

    public function testNumPlaceholderCapturesDigitsOnly(): void
    {
        $pattern = $this->pattern()->forUriPattern('/users/(:num)');

        $matched = $pattern->check($this->request(), '/users/15');
        self::assertNotNull($matched);
        self::assertSame(['15'], array_values($matched->getMethodParams()));

        self::assertNull($pattern->check($this->request(), '/users/abc'));
    }

    public function testAnyPlaceholderDoesNotCrossSlash(): void
    {
        $pattern = $this->pattern()->forUriPattern('/page/(:any)');

        self::assertNotNull($pattern->check($this->request(), '/page/about'));
        self::assertNull($pattern->check($this->request(), '/page/about/deep'));
    }

    public function testAllPlaceholderCrossesSlash(): void
    {
        $pattern = $this->pattern()->forUriPattern('/files(:all)');

        self::assertNotNull($pattern->check($this->request(), '/files/a/b/c'));
    }

    public function testMethodRestrictionApplies(): void
    {
        $pattern = $this->pattern()
            ->forUriPattern('/hello')
            ->forMethod('POST');

        self::assertNull($pattern->check($this->request('GET'), '/hello'));
        self::assertNotNull($pattern->check($this->request('POST'), '/hello'));
    }

    public function testDomainRestrictionApplies(): void
    {
        $pattern = $this->pattern()
            ->forUriPattern('/hello')
            ->forDomain('allowed.test');

        self::assertNull($pattern->check($this->request('GET', 'other.test'), '/hello'));
        self::assertNotNull($pattern->check($this->request('GET', 'allowed.test'), '/hello'));
    }

    public function testForDomainNullMeansNoRestriction(): void
    {
        $pattern = $this->pattern()
            ->forUriPattern('/hello')
            ->forDomain(null);

        self::assertNotNull($pattern->check($this->request('GET', 'anything.test'), '/hello'));
    }

    public function testForDomainsAcceptsEveryListedDomain(): void
    {
        $pattern = $this->pattern()
            ->forUriPattern('/hello')
            ->forDomains(['a.test', 'b.test']);

        self::assertNotNull($pattern->check($this->request('GET', 'a.test'), '/hello'));
        self::assertNotNull($pattern->check($this->request('GET', 'b.test'), '/hello'));
        self::assertNull($pattern->check($this->request('GET', 'c.test'), '/hello'));
    }

    public function testMiddlewaresArePropagatedToEntryPoint(): void
    {
        $entryPoint = $this->pattern()
            ->forUriPattern('/hello')
            ->withMiddlewares(['mw.one', 'mw.two'])
            ->check($this->request(), '/hello');

        self::assertSame(['mw.one', 'mw.two'], $entryPoint->getMiddlewareServiceNames());
    }

    public function testToKeyStringIsStable(): void
    {
        $key = $this->pattern()
            ->forUriPattern('/hello')
            ->forMethod('get')
            ->forDomain('a.test')
            ->toKeyString();

        self::assertSame('GET|a.test|/hello', $key);
    }

    public function testControllerShorthandResolvesCamelBased(): void
    {
        $entryPoint = RoutingPattern::controller(CamelController::class)
            ->forUriPattern('/(:all)')
            ->check($this->request(), '/hello/john');

        self::assertNotNull($entryPoint);
        self::assertSame(CamelController::class, $entryPoint->getControllerName());
        self::assertSame('helloAction', $entryPoint->getMethodName());
    }

    public function testControllerSnakeShorthandResolvesSnakeBased(): void
    {
        $entryPoint = RoutingPattern::controllerSnake(SnakeController::class)
            ->forUriPattern('/(:all)')
            ->check($this->request(), '/hello');

        self::assertNotNull($entryPoint);
        self::assertSame(SnakeController::class, $entryPoint->getControllerName());
        self::assertSame('hello_action', $entryPoint->getMethodName());
    }

    public function testMethodShorthandResolvesToGivenMethod(): void
    {
        $entryPoint = RoutingPattern::method(RecordingController::class, 'indexAction')
            ->forUriPattern('/hello')
            ->check($this->request(), '/hello');

        self::assertNotNull($entryPoint);
        self::assertSame(RecordingController::class, $entryPoint->getControllerName());
        self::assertSame('indexAction', $entryPoint->getMethodName());
    }
}
