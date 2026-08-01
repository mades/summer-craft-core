<?php

namespace SummerCraft\Core\Tests\Unit\EventDispatcher;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SummerCraft\Core\ComponentManaging\ComponentHolder;
use SummerCraft\Core\ComponentManaging\Config\Config;
use SummerCraft\Core\ComponentManaging\RequestScope;
use SummerCraft\Core\EventDispatcher\DefaultEventDispatcher;
use SummerCraft\Core\EventDispatcher\EventsConfig;
use SummerCraft\Core\EventDispatcher\SimpleEvent;
use SummerCraft\Core\Tests\Fixture\Component\ScopedFixture;
use SummerCraft\Core\Tests\Fixture\RecordingSubscriber;

class DefaultEventDispatcherTest extends TestCase
{
    private RequestScope $scope;

    protected function setUp(): void
    {
        $this->scope = new RequestScope(new ComponentHolder(new Config()));
    }

    private function dispatcher(array $events = []): DefaultEventDispatcher
    {
        $config = new EventsConfig();
        $config->events = $events;
        return new DefaultEventDispatcher($this->scope, $config);
    }

    public function testSubscriberReceivesFiredEvent(): void
    {
        $subscriber = new RecordingSubscriber($this->scope);
        $this->scope->set('sub.recording', $subscriber);

        $dispatcher = $this->dispatcher();
        $dispatcher->subscribe('thing.happened', 'sub.recording');

        $event = new SimpleEvent('thing.happened', ['k' => 'v']);
        $dispatcher->fire($event);

        self::assertSame([$event], $subscriber->caught);
        self::assertSame(['k' => 'v'], $subscriber->caught[0]->getData());
    }

    public function testSubscriptionsFromConfigAreRegistered(): void
    {
        $subscriber = new RecordingSubscriber($this->scope);
        $this->scope->set('sub.configured', $subscriber);

        $dispatcher = $this->dispatcher([['thing.happened', 'sub.configured']]);
        $dispatcher->fire(new SimpleEvent('thing.happened', []));

        self::assertCount(1, $subscriber->caught);
    }

    public function testEventWithoutSubscribersIsIgnored(): void
    {
        $dispatcher = $this->dispatcher();
        $dispatcher->fire(new SimpleEvent('nobody.listens', []));
        $this->addToAssertionCount(1); // no exception is the assertion
    }

    public function testInvalidSubscriberTypeFails(): void
    {
        $this->scope->set('sub.invalid', new ScopedFixture());

        $dispatcher = $this->dispatcher();
        $dispatcher->subscribe('thing.happened', 'sub.invalid');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid implementation');
        $dispatcher->fire(new SimpleEvent('thing.happened', []));
    }

    public function testDispatcherIsCreatedPerRequestScope(): void
    {
        $config = new Config();
        $config->services[\SummerCraft\Core\EventDispatcher\EventDispatcher::class] =
            \SummerCraft\Core\ComponentManaging\Config\ComponentConfig::forClass(DefaultEventDispatcher::class);
        $holder = new ComponentHolder($config);

        $scopeA = new RequestScope($holder);
        $scopeB = new RequestScope($holder);

        $dispatcherA = $scopeA->get(\SummerCraft\Core\EventDispatcher\EventDispatcher::class);
        $dispatcherB = $scopeB->get(\SummerCraft\Core\EventDispatcher\EventDispatcher::class);

        self::assertInstanceOf(DefaultEventDispatcher::class, $dispatcherA);
        // same instance within one request, a fresh one per request
        self::assertSame($dispatcherA, $scopeA->get(\SummerCraft\Core\EventDispatcher\EventDispatcher::class));
        self::assertNotSame($dispatcherA, $dispatcherB);
    }

    public function testMultipleSubscribersAllReceiveEvent(): void
    {
        $first = new RecordingSubscriber($this->scope);
        $second = new RecordingSubscriber($this->scope);
        $this->scope->set('sub.first', $first);
        $this->scope->set('sub.second', $second);

        $dispatcher = $this->dispatcher();
        $dispatcher->subscribe('thing.happened', 'sub.first');
        $dispatcher->subscribe('thing.happened', 'sub.second');
        $dispatcher->fire(new SimpleEvent('thing.happened', []));

        self::assertCount(1, $first->caught);
        self::assertCount(1, $second->caught);
    }
}
