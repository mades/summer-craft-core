<?php

namespace SummerCraft\Core\Tests\Unit\Response;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use SummerCraft\Core\ComponentManaging\ComponentHolder;
use SummerCraft\Core\ComponentManaging\Config\Config;
use SummerCraft\Core\ComponentManaging\Config\ComponentConfig;
use SummerCraft\Core\ComponentManaging\RequestScope;
use SummerCraft\Core\EventDispatcher\DefaultEventDispatcher;
use SummerCraft\Core\EventDispatcher\EventDispatcher;
use SummerCraft\Core\Http\ResponseAccumulator;
use SummerCraft\Core\Http\ServerRequest;
use SummerCraft\Core\Http\StatusCode;
use SummerCraft\Core\Response\SpecificResponseHandler;

class SpecificResponseHandlerTest extends TestCase
{
    private RequestScope $scope;

    protected function setUp(): void
    {
        $config = new Config();
        $config->services[EventDispatcher::class] = ComponentConfig::forClass(DefaultEventDispatcher::class);

        $this->scope = new RequestScope(new ComponentHolder($config));
        $this->scope->set(ServerRequestInterface::class, new ServerRequest('GET', 'http://app.test/x'));
    }

    public function testErrorBadRequestSetsStatus400AndBadRequestText(): void
    {
        $this->scope->get(SpecificResponseHandler::class)->errorBadRequest();

        $response = $this->scope->get(ResponseAccumulator::class)->toResponse();
        self::assertSame(StatusCode::BAD_REQUEST, $response->getStatusCode());
        self::assertStringContainsString('400 Bad Request', (string)$response->getBody());
    }

    public function testErrorNotFoundSetsStatus404(): void
    {
        $this->scope->get(SpecificResponseHandler::class)->errorNotFound();

        $response = $this->scope->get(ResponseAccumulator::class)->toResponse();
        self::assertSame(StatusCode::NOT_FOUND, $response->getStatusCode());
        self::assertStringContainsString('404 Not found', (string)$response->getBody());
    }
}
