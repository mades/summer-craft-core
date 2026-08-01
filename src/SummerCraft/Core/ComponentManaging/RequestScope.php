<?php

namespace SummerCraft\Core\ComponentManaging;

use SummerCraft\Core\Request\RequestIdentity;

class RequestScope
{
    private RequestIdentity $identity;

    public function __construct(
        private ComponentHolder $componentHolder,
    ) {
        $this->identity = RequestIdentity::createUnique();
        $this->componentHolder->set(RequestScope::class, $this->identity, $this);
    }

    /**
     * Get component by key
     * @template T of object
     * @param class-string<T> $componentName Component name or className
     * @return T
     */
    public function get(string $componentName): object
    {
        return $this->componentHolder->get($componentName, $this->getIdentity());
    }

    public function has(string $componentName): bool
    {
        return $this->componentHolder->has($componentName, $this->getIdentity());
    }

    public function set(string $componentName, object $component): void
    {
        $this->componentHolder->set($componentName, $this->getIdentity(), $component);
    }

    public function getIdentity(): RequestIdentity
    {
        return $this->identity;
    }
}
