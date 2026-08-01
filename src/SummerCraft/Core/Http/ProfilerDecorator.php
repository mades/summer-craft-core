<?php

namespace SummerCraft\Core\Http;

use SummerCraft\Core\Autoloader;
use SummerCraft\Core\BenchmarkHolder;
use SummerCraft\Core\ComponentManaging\ComponentHolder;
use SummerCraft\Core\ComponentManaging\LifeCycle\RequestScopeComponent;
use SummerCraft\Core\ComponentManaging\RequestScope;
use SummerCraft\Core\Response\ResponseConfig;

/**
 * Replaces profiler placeholders in the response body
 * (extracted from the removed DefaultResponse::send()).
 */
class ProfilerDecorator implements RequestScopeComponent
{
    public function __construct(
        private ComponentHolder $componentHolder,
        private ResponseConfig $config,
        private BenchmarkHolder $benchmark,
    ) {
    }

    public function apply(string $output): string
    {
        if (!$this->config->profiler) {
            return $output;
        }
        $this->benchmark->point('ResponseSend');

        if (str_contains($output, '{#result_time_table}')) {
            $output = str_replace('{#result_time_table}', $this->benchmark->benchmarkTotalTimeTable(), $output);
        }
        if (str_contains($output, '{#result_class_table}')) {
            // Application run with castom autoloader, not composer
            if ($this->componentHolder->settled(Autoloader::class)) {
                $autoloader = $this->componentHolder->get(Autoloader::class, null);
                $output = str_replace('{#result_class_table}', $this->benchmark->benchmarkTotalLoadedTable($autoloader->getLoadedClasses()), $output);
            } else {
                $output = str_replace('{#result_class_table}', 'No autoloader found', $output);
            }
        }
        if (str_contains($output, '{#result_time}')) {
            $output = str_replace('{#result_time}', $this->benchmark->elapsedString('BEFORE_SEND_RESPONSE'), $output);
        }
        if (str_contains($output, '{#result_memory}')) {
            $output = str_replace('{#result_memory}', $this->benchmark->usedMemoryAsString(), $output);
        }
        return $output;
    }
}
