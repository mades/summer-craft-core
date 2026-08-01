<?php

namespace SummerCraft\Core\ExceptionProcessing;

use SummerCraft\Core\ComponentManaging\LifeCycle\RequestScopeComponent;
use Throwable;

/**
 * Class to share exception from handler to custom entrypoint.
 * It is need because entry points can get from parameters ony strings
 */
class ThrowableContext implements RequestScopeComponent
{
    private Throwable $throwable;

    public function getThrowable(): ?Throwable
    {
        return $this->throwable ?? null;
    }

    public function setThrowable(Throwable $throwable): void
    {
        $this->throwable = $throwable;
    }
}
