<?php

namespace SummerCraft\Core\ComponentManaging;

use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use RuntimeException;
use SummerCraft\Core\ComponentManaging\LifeCycle\RequestScopeComponent;
use SummerCraft\Core\ComponentManaging\LifeCycle\SharedComponent;
use SummerCraft\Core\Request\RequestIdentity;
use SummerCraft\Core\Exception\ComponentException;
use Throwable;

/**
 * Create component throw reflection. for child components calls ComponentHolder
 *
 * Checks scope of components:
 * Request-scoped components can have shared, transient and request-scoped children
 * Shared and transient components can have shared and transient children
 * So: Shared components can not have request-scoped children. A transient
 * child is fine — it's just a fresh instance captured at creation time,
 * it doesn't carry data from a specific request.
 *
 */
class ComponentCreator
{
    private array $serviceStack = [];

    private int $recursiveCall = 0;

    public function createComponentWithReflection(
        ComponentHolder $serviceHolder,
        ComponentCreator $recursiveCreator,
        string $className,
        ?RequestIdentity $requestIdentity
    ) {
        if ($recursiveCreator->recursiveCall > 127) {
            throw ComponentException::onRecursiveComponentCreation($className, $this->serviceStack);
        }

        // A direct cycle (A -> B -> A) revisits a class still on the stack — no need to
        // wait for the depth counter above to catch it 127 frames later.
        if (isset($this->serviceStack[$className])) {
            throw ComponentException::onRecursiveComponentCreation($className, $this->serviceStack);
        }

        $this->serviceStack[$className] = true;

        try {
            /**
             * Investigating of usage Reflection for autowiring classes has shown that it get small impact on perfomance
             * https://github.com/brainfoolong/php-reflection-performance-tests
             *
             * In addition, a benchmark comparison of the old approach and the new approach showed
             * that the response time remained generally the same.
             */
            $reflectionClass = new ReflectionClass($className);

            $resultParams = [];
            $reflectionConstructor = $reflectionClass->getConstructor();
            if ($reflectionConstructor) {
                foreach ($reflectionConstructor->getParameters() as $parameter) {
                    if ($parameter->isOptional()) {
                        $resultParams[] = $parameter->getDefaultValue();
                        continue;
                    }
                    $parameterType = $parameter->getType();
                    if (!$parameterType instanceof ReflectionNamedType) {
                        throw new RuntimeException(sprintf(
                            'Service [%s] can not be created. Reason: Parameter type is not specified. %s',
                            $className,
                            json_encode($this->serviceStack, JSON_PRETTY_PRINT)
                        ));
                    }

                    $recursiveCreator->recursiveCall++;
                    // ComponentHolder::get() returns an object or throws — no null check needed
                    $resultParams[] = $serviceHolder->get($parameterType->getName(), $requestIdentity, $recursiveCreator);
                }
            }
        } catch (ReflectionException $exception) {
            throw new RuntimeException(sprintf(
                'Service [%s] can not be created. Reason: %s. Current AutoWire Classes: %s',
                $className,
                $exception->getMessage(),
                json_encode(array_keys($this->serviceStack), JSON_PRETTY_PRINT)
            ));
        }

        unset($this->serviceStack[$className]);

        if ($reflectionClass->implementsInterface(SharedComponent::class)) {
            foreach ($resultParams as $resultParam) {
                if ($resultParam instanceof RequestScopeComponent) {
                    throw new RuntimeException(sprintf(
                        "Service (%s) is in SharedScope and has dependency of Service (%s) in RequestScope",
                        $className,
                        get_class($resultParam)
                    ));
                }
            }
        }

        try {
            return new $className(...$resultParams);
        } catch (Throwable $exception) {
            throw new RuntimeException(sprintf(
                'Service [%s] can not be created. Reason: %s. Current AutoWire Classes: %s',
                $className,
                $exception->getMessage(),
                json_encode(array_keys($this->serviceStack), JSON_PRETTY_PRINT)
            ));
        }
    }

}
