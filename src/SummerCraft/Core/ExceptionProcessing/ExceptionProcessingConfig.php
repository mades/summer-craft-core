<?php

namespace SummerCraft\Core\ExceptionProcessing;

use SummerCraft\Core\ComponentManaging\LifeCycle\SharedComponent;

class ExceptionProcessingConfig implements SharedComponent
{
    public ?int $debugBacktraceType = ExceptionProcessor::BACKTRACE_TYPE_DEFAULT_SPECIFIC;
    public bool $showErrors = true;
    public bool $showCliErrors = true;
}