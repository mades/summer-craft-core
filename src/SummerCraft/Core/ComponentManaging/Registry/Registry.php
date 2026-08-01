<?php

namespace SummerCraft\Core\ComponentManaging\Registry;

use ArrayObject;
use RuntimeException;
use SummerCraft\Core\ComponentManaging\RequestScope;
use Traversable;

class Registry
{
    public function __construct(
        private RegistryConfig $registryConfig,
        private RequestScope $requestScope,
    ) {
    }

    /**
     * Every service registered as implementing $interfaceClassName.
     *
     * @template T
     * @param class-string<T> $interfaceClassName
     * @return Traversable<integer, T>
     */
    public function get(string $interfaceClassName): Traversable
    {
        $result = [];
        if (isset($this->registryConfig->services[$interfaceClassName])) {
            ksort($this->registryConfig->services[$interfaceClassName]);
            foreach ($this->registryConfig->services[$interfaceClassName] as $serviceName) {
                $this->assertImplements($interfaceClassName, $serviceName);
                $result[] = $this->requestScope->get($serviceName);
            }
        }
        return new ArrayObject($result);
    }

    /**
     * The promise of a registry is "everything implementing this", and until now
     * nothing checked it: the interface name was a string key, so a typo or an
     * `implements` dropped in a refactor surfaced as the consumer receiving an
     * object of the wrong type, far from the cause.
     *
     * Checked here rather than in RegistryConfig::add(): add() runs for every
     * module at boot, and is_subclass_of() on a class name autoloads it — so
     * checking there would load every registered class on every request, even
     * the lists nobody asks for. Here the class is about to be instantiated
     * anyway, and the check happens before that, not after.
     */
    private function assertImplements(string $interfaceClassName, string $serviceName): void
    {
        if ($serviceName === $interfaceClassName || is_subclass_of($serviceName, $interfaceClassName)) {
            return;
        }
        throw new RuntimeException(sprintf(
            'Registry has %s registered as %s, which it does not implement or extend.'
            . ' Either the name is a typo, or the class lost its "implements" in a refactor.',
            $serviceName,
            $interfaceClassName
        ));
    }
}
