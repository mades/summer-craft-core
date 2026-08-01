<?php

namespace SummerCraft\Core\Response;

use SummerCraft\Core\ComponentManaging\LifeCycle\SharedComponent;

class ResponseConfig implements SharedComponent
{
    public string $charset = 'UTF-8';

    /**
     * Placeholders:
     *   {#result_time_table}
     *   {#result_class_table}
     *   {#result_time}
     *   {#result_memory}
     */
    public bool $profiler = false;
}