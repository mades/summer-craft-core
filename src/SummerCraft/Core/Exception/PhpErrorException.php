<?php

namespace SummerCraft\Core\Exception;

use Exception;

class PhpErrorException extends Exception
{
    protected int $severity;

    protected string $errorFile;

    protected int $errorLine;

    public function setSeverity(int $severity): void
    {
        $this->severity = $severity;
    }

    public function setErrorFile(string $errorFile): void
    {
        $this->errorFile = $errorFile;
    }

    public function setErrorLine(int $errorLine): void
    {
        $this->errorLine = $errorLine;
    }

    public function getSeverity(): int
    {
        return $this->severity;
    }

    public function getErrorFile(): string
    {
        return $this->errorFile;
    }

    public function getErrorLine(): int
    {
        return $this->errorLine;
    }
}
