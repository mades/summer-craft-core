<?php

namespace SummerCraft\Core\Tests\Fixture\Routing;

use SummerCraft\Core\ComponentManaging\LifeCycle\RequestScopeComponent;
use SummerCraft\Core\Routing\Exception\BadRequestException;

/** Records every action call so Router tests can assert execution */
class RecordingController implements RequestScopeComponent
{
    /** @var array<int, array{method: string, params: array}> */
    public static array $calls = [];

    public static function reset(): void
    {
        self::$calls = [];
    }

    public function indexAction(string ...$params): void
    {
        self::$calls[] = ['method' => 'indexAction', 'params' => $params];
    }

    public function notFoundAction(string ...$params): void
    {
        self::$calls[] = ['method' => 'notFoundAction', 'params' => $params];
    }

    public function badRequestEntryAction(string ...$params): void
    {
        self::$calls[] = ['method' => 'badRequestEntryAction', 'params' => $params];
    }

    public function serverErrorAction(string ...$params): void
    {
        self::$calls[] = ['method' => 'serverErrorAction', 'params' => $params];
    }

    public function psrAction(): \SummerCraft\Core\Http\Response
    {
        self::$calls[] = ['method' => 'psrAction', 'params' => []];
        return \SummerCraft\Core\Http\Response::html('<p>psr</p>', 201);
    }

    public function badRequestAction(): void
    {
        self::$calls[] = ['method' => 'badRequestAction', 'params' => []];
        throw new BadRequestException('bad input');
    }

    public function brokenAction(): void
    {
        self::$calls[] = ['method' => 'brokenAction', 'params' => []];
        throw new \RuntimeException('boom');
    }
}
