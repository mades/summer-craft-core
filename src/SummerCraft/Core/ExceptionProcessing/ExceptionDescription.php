<?php

namespace SummerCraft\Core\ExceptionProcessing;

use SummerCraft\Core\Application;
use SummerCraft\Core\Exception\PhpErrorException;
use Throwable;

class ExceptionDescription
{
    private const SEVERITY_TO_STRING_MAP = [
        E_PARSE => 'E_PARSE',
        E_ERROR => 'E_ERROR',
        E_CORE_ERROR => 'E_CORE_ERROR',
        E_COMPILE_ERROR => 'E_COMPILE_ERROR',
        E_USER_ERROR => 'E_USER_ERROR', // Fatal error // log error

        E_WARNING => 'E_WARNING',
        E_USER_WARNING => 'E_USER_WARNING',
        E_COMPILE_WARNING => 'E_COMPILE_WARNING',
        E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR', // Warning

        E_NOTICE => 'E_NOTICE',
        E_USER_NOTICE => 'E_USER_NOTICE', // Notice

        // E_STRICT is gone from the engine since PHP 8.0 and the constant itself is
        // deprecated as of PHP 8.4 (referencing it emits a deprecation notice) — dropped.
        E_DEPRECATED => 'E_DEPRECATED', // Notice
        E_USER_DEPRECATED => 'E_USER_DEPRECATED', // Notice
    ];

    public function __construct(
        private Throwable $throwable,
    ) {
    }

    public function getExceptionTitle(): string
    {
        if ($this->throwable instanceof PhpErrorException) {
            $severityTitle = self::SEVERITY_TO_STRING_MAP[$this->throwable->getSeverity()]
                ?? "N/A({$this->throwable->getSeverity()})";
            return "A PHP Error [$severityTitle] was encountered";
        }
        return "An uncaught Exception was encountered";
    }

    public function getExceptionType(): string
    {
        return get_class($this->throwable);
    }

    public function getMessage(): string
    {
        return $this->throwable->getMessage();
    }

    public function getFileName(): string
    {
        if ($this->throwable instanceof PhpErrorException) {
            return $this->throwable->getErrorFile();
        }
        return $this->throwable->getFile();
    }

    public function getFileLine(): int
    {
        if ($this->throwable instanceof PhpErrorException) {
            return $this->throwable->getErrorLine();
        }
        return $this->throwable->getLine();
    }

    public function getBacktraceAsString(): string
    {
        return $this->throwable->getTraceAsString();
    }

    public function getBacktraceArray(): array
    {
        $result = [];
        foreach ($this->throwable->getTrace() as $key => $error) {
            if (isset($error['file'])) {
                $filePath = $this->sanitizeBasePath($error['file']);
                $result[$key] = "{$filePath}:{$error['line']} {$error['function']}()";
            }
        }
        return $result;
    }

    private function sanitizeBasePath(string $input): string
    {
        if (!Application::hasInstance()) {
            return $input;
        }
        $basePath = Application::getInstance()->getContext()->getBasePath();
        return str_replace($basePath, 'BASE_PATH/', $input);
    }

    public function isError(): bool
    {
        if ($this->throwable instanceof PhpErrorException) {
            switch ($this->throwable->getSeverity()) {
                case E_PARSE:
                case E_ERROR:
                case E_CORE_ERROR:
                case E_COMPILE_ERROR:
                case E_USER_ERROR:
                    return true;
                default:
                    return false;
            }
        }
        return true;
    }

    public function logLevel(): string
    {
        if ($this->throwable instanceof PhpErrorException) {
            switch ($this->throwable->getSeverity()) {
                case E_PARSE:
                case E_ERROR:
                case E_CORE_ERROR:
                case E_COMPILE_ERROR:
                case E_USER_ERROR:
                    return 'critical';
                case E_WARNING:
                case E_USER_WARNING:
                case E_COMPILE_WARNING:
                case E_RECOVERABLE_ERROR:
                    return 'warning';
                case E_NOTICE:
                case E_USER_NOTICE:
                case E_DEPRECATED:
                case E_USER_DEPRECATED:
                    return 'notice';
                default:
                    return 'alert';
            }
        }
        return 'critical';
    }
}