<?php
namespace SummerCraft\Core\Exception;

use RuntimeException;

class ComponentException extends RuntimeException
{
    public static function onRecursiveComponentCreation(string $serviceName, array $serviceStack): ComponentException
    {
        return new self(
            "Recursive component [$serviceName] creation found: " . print_r($serviceStack, true)
        );
    }

    public static function onServiceValidationNotPassed(
        string $serviceName,
        string $resultType,
        string $expectedType
    ): ComponentException {
        return new self(
            "Class [$serviceName] provide object with validation fail. Expect: {$expectedType}, got {$resultType}"
        );
    }


}
