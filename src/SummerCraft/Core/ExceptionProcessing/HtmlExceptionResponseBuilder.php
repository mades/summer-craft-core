<?php

namespace SummerCraft\Core\ExceptionProcessing;

class HtmlExceptionResponseBuilder
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

            $fileName = self::escape($exceptionDescription->getFileName());
            $fileLine = $exceptionDescription->getFileLine();

            $backtraceBlock = '';
            if ($showDebugBacktraceType === ExceptionProcessor::BACKTRACE_TYPE_DEFAULT_SPECIFIC) {

                $backtraceLinesBlock = '';
                foreach ($exceptionDescription->getBacktraceArray() as $key => $line) {
                    $deep = $key + 1;
                    $escapedLine = self::escape($line);
                    $backtraceLinesBlock .= <<<ENDOFTEXT
                        <tr>
                            <td style="padding: 2px 10px">$deep : {$escapedLine}</td>
                        </tr>
                    ENDOFTEXT;
                }

                $backtraceBlock .= <<<ENDOFTEXT

                <p>Backtrace:</p>
                <table>
                    <tr>
                        <th style="padding: 2px 10px">File:Line - Function</th>
                    </tr>
                    <tr>
                        <td style="padding: 2px 10px">0 : {$fileName}:{$fileLine}</td>
                    </tr>
                    $backtraceLinesBlock
                </table>

                ENDOFTEXT;

            } elseif ($showDebugBacktraceType === ExceptionProcessor::BACKTRACE_TYPE_DEFAULT_WITH_PARAMETER) {

                $escapedBacktrace = self::escape($exceptionDescription->getBacktraceAsString());
                $backtraceBlock .= <<<ENDOFTEXT

                <p>Backtrace:</p>
                <pre>
                    {$escapedBacktrace}
                </pre>

                ENDOFTEXT;
            }

            $exceptionTitle = self::escape($exceptionDescription->getExceptionTitle());
            $exceptionType = self::escape($exceptionDescription->getExceptionType());
            $message = self::escape($exceptionDescription->getMessage());

            $result .= <<<ENDOFTEXT

            <div style="border:1px solid #990000;padding-left:20px;margin:0 0 10px 0;">

            <h4><span>[!APP-FAILED!]</span> {$exceptionTitle}</h4>

            <p>Type: {$exceptionType}</p>
            <p>Message: <pre>{$message}</pre></p>
            <p>Filename Line: {$fileName}:{$fileLine}</p>

            $backtraceBlock

            </div>

            ENDOFTEXT;
        }

        return $result;
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private static function buildGeneral(string $errorTitle, string $heading, string $message): string
    {
        $escapedTitle = self::escape($errorTitle);
        $escapedHeading = self::escape($heading);
        $escapedMessage = self::escape($message);

        return <<<ENDOFTEXT
        <!DOCTYPE html>
        <html lang="en">
        <head>
        <meta charset="utf-8">
        <title>{$escapedTitle}</title>
        <style>

        ::selection { background-color: #E13300; color: white; }
        ::-moz-selection { background-color: #E13300; color: white; }

        body {
            background-color: #fff;
            margin: 40px;
            font: 13px/20px normal Helvetica, Arial, sans-serif;
            color: #4F5155;
        }

        a {
            color: #003399;
            background-color: transparent;
            font-weight: normal;
        }

        h1 {
            color: #444;
            background-color: transparent;
            border-bottom: 1px solid #D0D0D0;
            font-size: 19px;
            font-weight: normal;
            margin: 0 0 14px 0;
            padding: 14px 15px 10px 15px;
        }

        code {
            font-family: Consolas, Monaco, Courier New, Courier, monospace;
            font-size: 12px;
            background-color: #f9f9f9;
            border: 1px solid #D0D0D0;
            color: #002166;
            display: block;
            margin: 14px 0 14px 0;
            padding: 12px 10px 12px 10px;
        }

        #container {
            margin: 10px;
            border: 1px solid #D0D0D0;
            box-shadow: 0 0 8px #D0D0D0;
        }

        p {
            margin: 12px 15px 12px 15px;
        }
        </style>
        </head>
        <body>
            <div id="container">
                <div>[!APP-FAILED!]</div>
                <h1>{$escapedHeading}</h1>
                <p>{$escapedMessage}</p>
            </div>
        </body>
        </html>

        ENDOFTEXT;
    }
}
