<?php

namespace SummerCraft\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SummerCraft\Core\Application;
use SummerCraft\Core\BenchmarkHolder;
use SummerCraft\Core\Context\ApplicationContext;
use SummerCraft\Core\Http\Psr17Factory;
use SummerCraft\Core\Http\ServerRequest;
use SummerCraft\Core\Tests\Fixture\RecordingSubscriber;
use SummerCraft\Core\Tests\Fixture\RequestStartConfigLoader;
use SummerCraft\Core\Tests\Fixture\ThrowingConfigLoader;

/**
 * What a process serving many requests needs and one serving a single request does
 * not: nothing may carry over, and a failure has to come back as a response rather
 * than be printed. Under FPM both were invisible — the process died either way.
 */
class WorkerReadinessTest extends TestCase
{
    private function application(string $configLoader = RequestStartConfigLoader::class): Application
    {
        return Application::create(ApplicationContext::create(
            isCli: true,
            configLoader: $configLoader,
            basePath: sys_get_temp_dir() . '/worker-readiness-test/',
        ));
    }

    public function testEachRequestBeginsWithTheMarkersOfNoOtherRequest(): void
    {
        // the holder is process-wide, so another test class running a request leaves
        // its markers here too — which is the very leak this test is about
        BenchmarkHolder::getInstance()->reset();
        RecordingSubscriber::forgetRequests();
        $application = $this->application();

        $application->run(new ServerRequest('GET', 'https://app.test/one'));
        $application->run(new ServerRequest('GET', 'https://app.test/two'));
        $application->run(new ServerRequest('GET', 'https://app.test/three'));

        // counted as each request begins, so what a previous one left behind would show
        $counts = RecordingSubscriber::markerCountsAtStart();
        self::assertCount(3, $counts);
        self::assertSame([$counts[0], $counts[0], $counts[0]], $counts, 'markers carried over between requests');
    }

    public function testTheFirstRequestStillMeasuresTheBoot(): void
    {
        BenchmarkHolder::getInstance()->reset();
        RecordingSubscriber::forgetRequests();
        $application = $this->application();

        // APP_START is set while the holder is built, during init(). Measured as the
        // request begins, not after it: a reset on the way in would have thrown the
        // boot away before anything could read it.
        $application->run(new ServerRequest('GET', 'https://app.test/one'));

        self::assertGreaterThan(0, RecordingSubscriber::elapsedAtStart()[0] ?? 0);
    }

    public function testAFailedRequestComesBackAsAResponse(): void
    {
        $application = $this->application(ThrowingConfigLoader::class);

        $response = $application->run(new ServerRequest('GET', 'https://app.test/boom'));

        // run() is typed non-nullable now, which is itself the guarantee: it used to
        // return null after printing, leaving a caller with nothing to send
        self::assertSame(500, $response->getStatusCode(), 'the status used to stay 200 with an error page in the body');
        self::assertStringContainsString('boom', (string)$response->getBody());
    }

    public function testEachRequestGetsItsOwnScope(): void
    {
        $application = $this->application();

        $first = $application->run(new ServerRequest('GET', 'https://app.test/one'));
        $second = $application->run(new ServerRequest('GET', 'https://app.test/two'));

        self::assertNotSame($first, $second);
    }

    public function testARuntimeCanDriveTheApplicationThroughPsr17(): void
    {
        // the shape of a worker loop: the runtime builds each request through PSR-17
        // and gets a response back, never touching a superglobal
        $factory = new Psr17Factory();
        $application = $this->application();

        $responses = [];
        foreach (['/one', '/two', '/three'] as $path) {
            $responses[] = $application->run(
                $factory->createServerRequest('GET', 'https://app.test' . $path, ['REMOTE_ADDR' => '10.0.0.1'])
            );
        }

        self::assertCount(3, $responses);
        foreach ($responses as $response) {
            self::assertGreaterThanOrEqual(200, $response->getStatusCode());
        }
    }
}
