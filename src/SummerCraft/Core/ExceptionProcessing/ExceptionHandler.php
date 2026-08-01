<?php

set_error_handler(
    function (int $severity, string $message, string $filePath = '', int $line = 0): bool
    {
        \SummerCraft\Core\ExceptionProcessing\ExceptionProcessor::defaultProcessError(
            $severity,
            $message,
            $filePath,
            $line
        );
        // false: let the standard PHP error handler run as well (previous behavior)
        return false;
    }
);
set_exception_handler(
    function ($exception): void
    {
        \SummerCraft\Core\ExceptionProcessing\ExceptionProcessor::defaultProcessException(
            $exception,
            null
        );
    }
);
register_shutdown_function(function (): void
    {
        $last_error = error_get_last();
        if (isset($last_error)
            && ($last_error['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_CORE_WARNING | E_COMPILE_ERROR | E_COMPILE_WARNING))
        ) {
            \SummerCraft\Core\ExceptionProcessing\ExceptionProcessor::defaultProcessError(
                $last_error['type'],
                $last_error['message'],
                $last_error['file'],
                $last_error['line']
            );
        }
    }
);
