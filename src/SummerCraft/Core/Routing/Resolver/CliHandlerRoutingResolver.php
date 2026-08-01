<?php

namespace SummerCraft\Core\Routing\Resolver;

use Psr\Log\LoggerInterface;
use RuntimeException;
use SummerCraft\Core\Cli\CliHandler;
use SummerCraft\Core\Routing\RoutingEntryPoint;

/**
 * Resolves a URI tail to a {@see CliHandler} implementation and calls its
 * handle() method:
 *
 *   php src/boot/cli.php handle App/Module/Some/SomeHandler
 *       -> App\Module\Some\SomeHandler::handle([])
 *   php src/boot/cli.php handle App/Module/Some/SomeHandler daily 10
 *       -> App\Module\Some\SomeHandler::handle(['daily', '10'])
 *
 * Register it yourself — nothing in the framework does, and without a pattern
 * no class is reachable this way:
 *
 *   $routerConfig->addPattern(
 *       RoutingPattern::resolveWith(CliHandlerRoutingResolver::create())
 *           ->forUriPatterns(['/handle/(:all)'])
 *           ->forMethod('CLI')
 *   );
 *
 * Keep ->forMethod('CLI') on that pattern. Without it the same entry point
 * answers ordinary HTTP requests, and every handler in the application becomes
 * a public, unauthenticated URL.
 *
 * Namespaces are written with "/", not "\": a backslash is not in
 * RequestConfig::$permittedUriChars, so the segment would be rejected before
 * routing runs at all.
 *
 * Where the class name ends and the arguments begin is decided by asking the
 * autoloader: the longest leading run of segments that names an existing class
 * wins, and whatever is left over is passed to handle(). A handler is normally
 * the only thing in that path that exists as a class, so this is unambiguous
 * in practice — but it does mean an argument that happens to complete a real
 * class name would be read as part of the name.
 */
class CliHandlerRoutingResolver implements RoutingResolver
{
    private function __construct()
    {
    }

    public static function create(): self
    {
        return new self();
    }

    public function getRoutingEntryPoint(array $uriMatchData, ?LoggerInterface $debugLogger = null): ?RoutingEntryPoint
    {
        $segments = array_values(array_filter(
            explode('/', $uriMatchData[1] ?? ''),
            static fn(string $segment): bool => $segment !== ''
        ));
        if ($segments === []) {
            if ($debugLogger) {
                $debugLogger->debug('CLI handler routing got no class name');
            }
            return null;
        }

        for ($length = count($segments); $length > 0; $length--) {
            $className = $this->toClassName(array_slice($segments, 0, $length));
            if (!class_exists($className, true)) {
                continue;
            }

            // exists but was not written as an entry point: say so instead of
            // falling through to a 404 that looks like a typo
            if (!is_subclass_of($className, CliHandler::class)) {
                throw new RuntimeException(
                    "Class {$className} does not implement " . CliHandler::class
                );
            }

            return new RoutingEntryPoint(
                $className,
                'handle',
                [array_slice($segments, $length)]
            );
        }

        if ($debugLogger) {
            $debugLogger->warning(
                'CLI handler routing found no class for ' . $this->toClassName($segments)
            );
        }
        return null;
    }

    /**
     * @param string[] $segments
     */
    private function toClassName(array $segments): string
    {
        return '\\' . implode('\\', array_map(ucfirst(...), $segments));
    }
}
