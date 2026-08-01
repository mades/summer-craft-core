<?php

namespace SummerCraft\Core\ExceptionProcessing;

class CliExceptionResponseBuilder
{
    public static function buildForDbError(string $heading, string $message): string
    {
        return self::buildGeneral('Database error', $heading, $message);
    }

    public static function buildForGeneral(string $heading, string $message): string
    {
        return self::buildGeneral('ERROR', $heading, $message);
    }

    /**
     * @param ExceptionDescription[] $exceptionDescriptions
     * @param int|null $showDebugBacktraceType
     */
    public static function buildForException(
        array $exceptionDescriptions,
        ?int $showDebugBacktraceType
    ): string {
        $result = '[!APP-FAILED!] ';

        foreach ($exceptionDescriptions as $exceptionDescription) {
            $result .= <<<ENDOFTEXT
            {$exceptionDescription->getExceptionTitle()}
            Type:        {$exceptionDescription->getExceptionType()}
            Message:     {$exceptionDescription->getMessage()}
            Filename:    {$exceptionDescription->getFileName()}:{$exceptionDescription->getFileLine()}
            
            ENDOFTEXT;

            if ($showDebugBacktraceType === ExceptionProcessor::BACKTRACE_TYPE_DEFAULT_SPECIFIC) {

                $result .= <<<ENDOFTEXT
                Backtrace:
                ENDOFTEXT;
                foreach ($exceptionDescription->getBacktraceArray() as $key => $traceElement) {
                    $stackNumber = $key + 1;
                    $result .= <<<ENDOFTEXT
                        {$stackNumber}: {$traceElement}
                        
                    ENDOFTEXT;
                }
                $result .= <<<ENDOFTEXT
                    
                ENDOFTEXT;

            } elseif ($showDebugBacktraceType === ExceptionProcessor::BACKTRACE_TYPE_DEFAULT_WITH_PARAMETER) {

                $result .= <<<ENDOFTEXT
                Backtrace:
                {$exceptionDescription->getBacktraceAsString()}
                
                
                ENDOFTEXT;
            }
        }

        return $result;
    }

    private static function buildGeneral(string $errorTitle, string $heading, string $message): string
    {
        return <<<ENDOFTEXT
        
        [!APP-FAILED!] {$errorTitle}: {$heading}
        
        {$message}
        
        ENDOFTEXT;
    }
}