<?php

namespace SummerCraft\Core\Tests\Unit\EventDispatcher;

use PHPUnit\Framework\TestCase;
use SummerCraft\Core\Application;
use SummerCraft\Core\Context\ApplicationContext;
use SummerCraft\Core\EventDispatcher\SimpleEvent;
use SummerCraft\Core\Http\ServerRequest;
use SummerCraft\Core\Tests\Fixture\RecordingSubscriber;
use SummerCraft\Core\Tests\Fixture\RequestStartConfigLoader;

/**
 * request.start used to fire before the request was built, and carried an empty
 * payload — so a subscriber could reach the request through neither the event nor
 * the container. The one subscriber this ever had read $_SERVER instead, which is
 * exactly the state a worker runtime must not be reading.
 */
class RequestStartEventTest extends TestCase
{
    private function application(): Application
    {
        return Application::create(ApplicationContext::create(
            isCli: true,
            configLoader: RequestStartConfigLoader::class,
            basePath: sys_get_temp_dir() . '/request-start-test/',
        ));
    }

    public function testSubscriberReceivesTheRequestInThePayload(): void
    {
        $application = $this->application();
        $request = new ServerRequest('GET', 'https://app.test/some/page');

        $application->run($request);

        $subscriber = $this->firedSubscriber($application);
        self::assertCount(1, $subscriber->caught, 'request.start fires once per request');

        $event = $subscriber->caught[0];
        self::assertInstanceOf(SimpleEvent::class, $event);
        self::assertSame($request, $event->getData()['request'] ?? null);
    }

    public function testTheRequestIsAlreadyInScopeWhenTheEventFires(): void
    {
        $application = $this->application();
        $request = new ServerRequest('GET', 'https://app.test/some/page');

        $application->run($request);

        $subscriber = $this->firedSubscriber($application);
        self::assertSame($request, $subscriber->resolvedFromScope);
    }

    private function firedSubscriber(Application $application): RecordingSubscriber
    {
        $subscriber = RecordingSubscriber::$lastInstance;
        self::assertInstanceOf(RecordingSubscriber::class, $subscriber, 'the subscriber never ran');

        return $subscriber;
    }
}
