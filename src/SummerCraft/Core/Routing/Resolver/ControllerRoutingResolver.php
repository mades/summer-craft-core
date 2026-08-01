<?php

namespace SummerCraft\Core\Routing\Resolver;

use Psr\Log\LoggerInterface;
use SummerCraft\Core\Routing\RoutingEntryPoint;

/**
 * Auto-found entry point by controller like:
 * /some1/some2/some3/some4/some5 -> \Some1Controller::some2Action(some3, some4)
 */
class ControllerRoutingResolver implements RoutingResolver
{
    private const METHOD_POSTFIX = 'Action';
    private const METHOD_SNAKE_POSTFIX = '_action';
    private const DEFAULT_METHOD_NAME = 'default';

    /**
     * @var string[]
     */
    private array $replaceParts = [];

    private array $disallowedParts = [];

    /**
     * How the regex capture groups produced by a URI pattern get split between
     * "fixed variables" and "the part used to pick the action + trailing params".
     *
     * A pattern like '/users/id(:num)/(:all)' compiles to a regex with one
     * capture group per (:num)/(:any)/(:all) placeholder, in the order they
     * appear (see RoutingPattern::check(), which feeds the raw preg_match()
     * result — $matches[1], $matches[2], ... — into getRoutingEntryPoint()
     * below as $uriMatchData).
     *
     * $variablesCount says: "the first N of those capture groups are plain
     * fixed values, not part of the action-picking segments — peel them off
     * the front and put them straight into $params, in order". Whatever
     * capture group comes right after them is the one this resolver actually
     * parses for the action name: its first '/'-separated segment becomes
     * `{segment}Action` (or `{segment}_action` for snakeBased()), and any
     * further segments are appended to $params after the fixed variables.
     *
     * === variablesCount = 0 (the default — no fixed variables at all) ===
     * The *first* capture group IS the action+params segment string.
     *
     *   ControllerRoutingResolver::camelBased(HomeController::class)
     *   ->forUriPatterns(['/', '/(:all)'])
     *
     *   /                 -> HomeController::defaultAction()
     *   /hello/john/doe   -> HomeController::helloAction('john', 'doe')
     *
     * === variablesCount = 1 (one fixed value before the action segment) ===
     * Register BOTH a "bare" pattern (no trailing group) and a "with tail"
     * pattern (with one) — they're just two alternatives tried in turn, not
     * one pattern with an optional group, so both cases need their own entry:
     *
     *   ControllerRoutingResolver::camelBased(UserController::class, 1)
     *   ->forUriPatterns(['/users/id(:num)', '/users/id(:num)/(:all)'])
     *
     *   /users/id42          -> UserController::defaultAction('42')
     *                           (no tail segment at all -> default action)
     *   /users/id42/comments -> UserController::commentsAction('42')
     *   /users/id42/posts/5  -> UserController::postsAction('42', '5')
     *                           (params = ['42'] fixed + ['5'] from the tail)
     *
     * === variablesCount = 2 (two fixed values before the action segment) ===
     * Same idea, just more leading capture groups peeled off before the one
     * used for action resolution; again pair a bare and a with-tail pattern:
     *
     *   ControllerRoutingResolver::camelBased(OrgUserController::class, 2)
     *   ->forUriPatterns(['/orgs(:num)/users(:num)', '/orgs(:num)/users(:num)/(:all)'])
     *
     *   /orgs7/users42          -> OrgUserController::defaultAction('7', '42')
     *   /orgs7/users42/posts/5  -> OrgUserController::postsAction('7', '42', '5')
     *
     * All the examples above were verified by actually running them through
     * RoutingPattern::check() + this resolver, not just reasoned about.
     */
    private int $variablesCount = 0;

    private string $methodPostfix = self::METHOD_POSTFIX;

    private function __construct(
        private string $controllerName,
    ) {
    }

    public static function camelBased(string $className, int $variablesCount = 0): self
    {
        $resolver = new self($className);
        $resolver->disallowedParts = ['__Hyphen__', '__Dot__'];
        $resolver->replaceParts = ['-' => '__Hyphen__', '.' => '__Dot__'];
        $resolver->variablesCount = $variablesCount;
        return $resolver;
    }

    public static function snakeBased(string $className, int $variablesCount = 0): self
    {
        $resolver = new self($className);
        $resolver->methodPostfix = self::METHOD_SNAKE_POSTFIX;
        $resolver->disallowedParts = ['__hyphen__', '__dot__'];
        $resolver->replaceParts = ['-' => '__hyphen__', '.' => '__dot__'];
        $resolver->variablesCount = $variablesCount;
        return $resolver;
    }

    public function getRoutingEntryPoint(array $uriMatchData, ?LoggerInterface $debugLogger = null): ?RoutingEntryPoint
    {
        $params = [];
        for ($i = 1; $i <= $this->variablesCount; $i++) {
            $params[] = $uriMatchData[$i];
            unset($uriMatchData[$i]);
        }
        $uriMatchData = array_values($uriMatchData);

        $segments = explode('/', $uriMatchData[1] ?? '');
        if ($segments[0] === '') {
            array_shift($segments);
        }

        if (!class_exists($this->controllerName, true)) {
            if ($debugLogger) {
                $debugLogger->warning("Controller routing entry point not found: {$this->controllerName}");
            }
            return null;
        }

        $testMethodName = self::DEFAULT_METHOD_NAME . $this->methodPostfix;
        if (!empty($segments)) {
            $testMethodName = $segments[0];
            foreach ($this->disallowedParts as $disallowedPart) {
                if (str_contains($testMethodName, $disallowedPart)) {
                    if ($debugLogger) {
                        $debugLogger->debug("Disallowed part {$testMethodName} is disallowed");
                    }
                    return null;
                }
            }
            foreach ($this->replaceParts as $replacePartsKey => $replacePartsValue) {
                $testMethodName = str_replace($replacePartsKey, $replacePartsValue, $testMethodName);
            }
            $testMethodName .= $this->methodPostfix;
            array_shift($segments);
        }



        if (!method_exists($this->controllerName, $testMethodName)) {
            if ($debugLogger) {
                $debugLogger->debug("Controller routing entry point does not have a method {$testMethodName}");
            }
            return null;
        }



        foreach ($segments as $segment) {
            $params[] = $segment;
        }

        return new RoutingEntryPoint(
            $this->controllerName,
            $testMethodName,
            $params
        );
    }
}
