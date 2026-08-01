<?php

namespace SummerCraft\Core\ComponentManaging\Registry;

use RuntimeException;
use SummerCraft\Core\ComponentManaging\LifeCycle\SharedComponent;

/**
 * Collects "every service implementing X" lists, which {@see Registry} then
 * resolves through the container. Modules fill it from their config loaders,
 * so a list is assembled from parts that do not know about each other.
 */
class RegistryConfig implements SharedComponent
{
    /**
     * @var string[][]
     */
    public array $services = [];

    /**
     * $ordering places a service at a fixed position; without it the service is
     * appended. Mixing the two within one interface is what to watch out for:
     * an appended service lands at max(index) + 1, so it can take the position
     * a later add() meant to claim explicitly, and that later call then fails
     * as a duplicate. Order a list either entirely by hand or not at all.
     */
    public function add(string $interfaceClassName, string $serviceName, ?int $ordering = null): void
    {
        if (!isset($this->services[$interfaceClassName])) {
            $this->services[$interfaceClassName] = [];
        }
        if ($ordering === null) {
            $this->services[$interfaceClassName][] = $serviceName;
        } else {
            if (isset($this->services[$interfaceClassName][$ordering])) {
                throw new RuntimeException("Duplicate ordering in Registry for $interfaceClassName");
            }
            $this->services[$interfaceClassName][$ordering] = $serviceName;
        }
    }
}
