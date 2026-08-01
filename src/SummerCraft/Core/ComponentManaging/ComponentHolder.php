<?php

namespace SummerCraft\Core\ComponentManaging;

use RuntimeException;
use SummerCraft\Core\ComponentManaging\Config\Config;
use SummerCraft\Core\ComponentManaging\Config\ComponentConfig;
use SummerCraft\Core\ComponentManaging\LifeCycle\SharedComponent;
use SummerCraft\Core\ComponentManaging\LifeCycle\TransientComponent;
use SummerCraft\Core\Request\RequestIdentity;
use SummerCraft\Core\Exception\ComponentException;

class ComponentHolder
{
    public function __construct(
        private Config $config,
    ) {
    }

    /**
     * @var object[] Container of loaded shared components(services)
     *               BY component(service) name
     */
    private array $sharedComponents = [];

    /**
     * @var object[][] Container of loaded shared components(services)
     *               BY scope name and component(service) name
     */
    private array $scopedComponents = [];

    /**
     * Get component by key: a class name or an arbitrary service alias.
     * @template T of object
     * @param class-string<T>|string $name Key of object
     * @param RequestIdentity|null $requestIdentity
     * @param ComponentCreator|null $recursiveCreator
     * @return ($name is class-string<T> ? T : object)
     */
    public function get(string $name, ?RequestIdentity $requestIdentity, ?ComponentCreator $recursiveCreator = null)
    {
        $requestScopeId = $requestIdentity ? $requestIdentity->getId() : '';
        if ($requestIdentity !== null && isset($this->scopedComponents[$requestScopeId][$name])) {
            return $this->scopedComponents[$requestScopeId][$name];
        }
        if (isset($this->sharedComponents[$name])) {
            return $this->sharedComponents[$name];
        }

        $serviceConfig = $this->config->services[$name] ?? null;
        $serviceClassName = isset($this->config->services[$name])
            ? $this->config->services[$name]->className
            : $name;

        if ($serviceClassName !== $name) {
            if ($requestIdentity !== null && isset($this->scopedComponents[$requestScopeId][$serviceClassName])) {
                $this->scopedComponents[$requestScopeId][$name] = $this->scopedComponents[$requestScopeId][$serviceClassName];
                return $this->scopedComponents[$requestScopeId][$name];
            }
            if (isset($this->sharedComponents[$serviceClassName])) {
                $this->sharedComponents[$name] = $this->sharedComponents[$serviceClassName];
                return $this->sharedComponents[$name];
            }
        }

        if (is_a($serviceClassName, TransientComponent::class, true)) {
            return $this->createInstance($name, $serviceClassName, $serviceConfig, $requestIdentity, $recursiveCreator);
        }

        if (is_a($serviceClassName, SharedComponent::class, true)) {
            $obj = $this->createInstance($name, $serviceClassName, $serviceConfig, $requestIdentity, $recursiveCreator);
            $this->sharedComponents[$name] = $obj;
            if ($serviceClassName !== $name) {
                $this->sharedComponents[$serviceClassName] = $obj;
            }
            return $obj;
        }
        if ($requestIdentity === null) {
            throw new RuntimeException(sprintf(
                "Trying create component (%s) without Request scope. You need mark component as (%s) or (%s)",
                $serviceClassName,
                SharedComponent::class,
                TransientComponent::class
            ));
        }
        $obj = $this->createInstance($name, $serviceClassName, $serviceConfig, $requestIdentity, $recursiveCreator);
        $this->scopedComponents[$requestScopeId][$name] = $obj;
        if ($serviceClassName !== $name) {
            $this->scopedComponents[$requestScopeId][$serviceClassName] = $obj;
        }
        return $obj;
    }

    /**
     * Creates a component instance either via its configured callback (validating the
     * result type) or via reflection-based autowiring. Shared across all three lifecycle
     * branches in {@see get()} — caching/scoping of the result is the caller's job.
     */
    private function createInstance(
        string $name,
        string $serviceClassName,
        ?ComponentConfig $serviceConfig,
        ?RequestIdentity $requestIdentity,
        ?ComponentCreator $recursiveCreator
    ): object {
        if ($serviceConfig !== null && $serviceConfig->isCallbackMethodCreation()) {
            $callback = $serviceConfig->callback;
            $obj = $callback($this, $requestIdentity);
            if (!is_a($obj, $serviceClassName)) {
                throw ComponentException::onServiceValidationNotPassed($name, get_class($obj), $serviceClassName);
            }
            return $obj;
        }
        $recursiveCreator = $recursiveCreator ?: new ComponentCreator();
        return $recursiveCreator->createComponentWithReflection($this, $recursiveCreator, $serviceClassName, $requestIdentity);
    }

    public function set(string $name, ?RequestIdentity $requestIdentity, $object): void
    {
        if ($requestIdentity !== null) {
            $this->scopedComponents[$requestIdentity->getId()][$name] = $object;
        } else {
            $this->sharedComponents[$name] = $object;
        }
    }

    public function settled(string $name, ?RequestIdentity $requestIdentity = null): bool
    {
        if ($requestIdentity !== null && isset($this->scopedComponents[$requestIdentity->getId()][$name])) {
            return true;
        }
        if (isset($this->sharedComponents[$name])) {
            return true;
        }
        return false;
    }

    /**
     * Whether {@see get()} can produce this component. Shared is always creatable.
     * Anything else (Transient included) is only guaranteed creatable when a request
     * identity is given — get() itself would create a bare Transient without one too,
     * but every real caller reaches has() through {@see RequestScope::has()}, which
     * always supplies an identity, so that case isn't covered here.
     * Does not attempt to verify the whole constructor dependency graph resolves — that
     * would mean attempting creation, which has() intentionally avoids.
     */
    public function has(string $name, ?RequestIdentity $requestIdentity = null): bool
    {
        if ($this->settled($name, $requestIdentity)) {
            return true;
        }

        $serviceConfig = $this->config->services[$name] ?? null;
        if ($serviceConfig !== null) {
            return true;
        }

        return (is_a($name, SharedComponent::class, true) && class_exists($name))
            || ($requestIdentity !== null && class_exists($name));
    }

    public function setSharedComponent(string $name, $object): void
    {
        $this->sharedComponents[$name] = $object;
    }

    public function destroyScope(RequestScope $requestScope): void
    {
        unset($this->scopedComponents[$requestScope->getIdentity()->getId()]);
    }
}
