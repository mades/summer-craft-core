<?php

namespace SummerCraft\Core\Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use SummerCraft\Core\BenchmarkHolder;
use SummerCraft\Core\ComponentManaging\ComponentHolder;
use SummerCraft\Core\ComponentManaging\Config\Config;
use SummerCraft\Core\Http\ProfilerDecorator;
use SummerCraft\Core\Http\Response;
use SummerCraft\Core\Http\SapiEmitter;
use SummerCraft\Core\Response\ResponseConfig;

class EmitterAndProfilerTest extends TestCase
{
    public function testEmitterEchoesBody(): void
    {
        ob_start();
        SapiEmitter::emit(Response::html('<p>out</p>'));
        $output = ob_get_clean();

        self::assertSame('<p>out</p>', $output);
    }

    private function profiler(bool $enabled): ProfilerDecorator
    {
        $holder = new ComponentHolder(new Config());
        $config = new ResponseConfig();
        $config->profiler = $enabled;
        return new ProfilerDecorator($holder, $config, BenchmarkHolder::getInstance());
    }

    public function testProfilerReplacesPlaceholdersWhenEnabled(): void
    {
        $output = $this->profiler(true)->apply('mem: {#result_memory}');

        self::assertStringNotContainsString('{#result_memory}', $output);
        self::assertStringContainsString('MB', $output);
    }

    public function testProfilerIsNoopWhenDisabled(): void
    {
        $body = 'mem: {#result_memory}';
        self::assertSame($body, $this->profiler(false)->apply($body));
    }
}
