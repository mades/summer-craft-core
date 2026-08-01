# Summer Craft Framework

[![tests](https://github.com/mades/summer-craft-core/actions/workflows/tests.yml/badge.svg)](https://github.com/mades/summer-craft-core/actions/workflows/tests.yml)

A minimal PHP 8.4+ framework core: dependency-injection container with reflection
autowiring and lifecycle scopes, router, request/response abstraction, event
dispatcher and an exception-processing pipeline. ~4k lines of code; the only
runtime dependencies are the PSR interface packages — no extensions beyond
PHP itself.

This is a personal framework built for learning and for running the author's own
projects. It is deliberately small and readable — the whole core can be read in an
evening. It does not try to compete with Laravel or Symfony: if you need an
ecosystem (ORM, auth, queues, packages, hiring pool), use those. If you want to
understand how a DI container or a router actually works — or run a tiny site with
no dependencies at all — this is the kind of codebase that shows it without
5 layers of abstraction.

## Boundaries

What belongs here: the request lifecycle and the machinery every application needs
whatever it does — DI, routing, PSR-7, events, exception processing, class loading.

What does not:

- infrastructure with an outside dependency — a database, mail, an HTTP client,
  files. That is [summer-craft-service-hub](../summer-craft-service-hub);

The core ships **no modules**, and the one feature that came close is instructive:
CLI dispatch lives here, but the core registers no route for it. `CliHandler` and
its resolver are available and the application decides whether to wire them up.
That opt-in is what makes the feature a mechanism rather than a module — a
distinction worth keeping when adding anything here.

## Features

- **DI container** — constructor autowiring via reflection, three lifecycle scopes
  with cross-scope safety checks and cycle detection:

  | Marker interface | Lifecycle |
  |---|---|
  | `SharedComponent` | one instance per application (singleton) |
  | `RequestScopeComponent` | one instance per request |
  | `TransientComponent` | new instance on every resolution |

  A shared component cannot depend on a transient/request-scoped one — the
  container fails loudly with the full creation stack instead of silently caching
  a stale dependency.

- **Router** — patterns with `(:num)` / `(:any)` / `(:all)` placeholders, optional
  domain and HTTP-method restrictions, middleware chains (PSR-15
  `MiddlewareInterface` and the legacy `Middleware` can be mixed in one chain),
  and four resolver
  strategies: explicit class::method mapping (`MethodRoutingResolver`),
  convention-based `/controller/action/params` (`ControllerRoutingResolver`,
  camelCase or snake_case), namespace-tree resolution
  (`NamespaceRoutingResolver`), and console entry points
  (`CliHandlerRoutingResolver`, see below). Built-in 400/404/500 entry points.

- **Events** — request-scoped dispatcher; subscribers are container services,
  subscriptions are declared in config.

- **Exception processing** — configurable handlers with HTML/CLI response
  builders and full creation-stack context in error messages.

- **Request/Response** — own PSR-7 implementation (`SummerCraft\Core\Http`) with
  typed input readers (`RequestInput`), a mutable per-request `ResponseAccumulator`
  for controllers, and a SAPI emitter; requests are easily faked in tests via
  `ServerRequestFactory`.

## Installation

The package is not published on Packagist. It is consumed as a
[path repository](https://getcomposer.org/doc/05-repositories.md#path) — clone it
next to your application:

```json
{
    "repositories": {
        "summer-craft-core": { "type": "path", "url": "../summer-craft-core" }
    },
    "require": {
        "mades/summer-craft-core": "@dev"
    }
}
```

## Quick start

```php
<?php // public/index.php

require __DIR__ . '/../vendor/autoload.php';

use SummerCraft\Core\Application;
use SummerCraft\Core\ComponentManaging\LifeCycle\RequestScopeComponent;
use SummerCraft\Core\ConfigLoader\CoreConfigLoader;
use SummerCraft\Core\Context\ApplicationContext;
use SummerCraft\Core\Response\Response;
use SummerCraft\Core\Routing\Resolver\ControllerRoutingResolver;
use SummerCraft\Core\Routing\RouterConfig;
use SummerCraft\Core\Routing\RoutingPattern;

class HomeController implements RequestScopeComponent
{
    public function __construct(private Response $response) {}

    public function defaultAction(): void
    {
        $this->response->append('Hello from Summer Craft');
    }

    public function greetAction(string $name = 'world'): void
    {
        $this->response->append('Hello, ' . htmlspecialchars($name));
    }
}

class AppConfigLoader extends CoreConfigLoader
{
    public function load(): void
    {
        parent::load();

        $this->componentHolder->get(RouterConfig::class, null)->addPattern(
            RoutingPattern::resolveWith(
                ControllerRoutingResolver::camelBased(HomeController::class)
            )->forUriPatterns(['/', '/(:all)'])
        );
    }
}

Application::configureErrorHandlers();

$app = Application::create(ApplicationContext::create(
    isCli: PHP_SAPI === 'cli',
    configLoader: AppConfigLoader::class,
    basePath: dirname(__DIR__) . '/',
));
$app->run(null)?->send();
```

`GET /` → `defaultAction()`, `GET /greet/John` → `greetAction('John')`.
Controller dependencies (here `Response`) are autowired by the container.

Larger applications split configuration into per-module loaders implementing
`ModuleConfigLoader` — each module registers its own routes, services and config
objects (see the `summer-craft-develop` application for real examples).

## CLI handlers

Console commands go through the same router as HTTP: the entry script turns
`$argv` into a request path and routes it under the pseudo-method `CLI`. On top
of that, `CliHandlerRoutingResolver` runs a class by name — no controller in
between, the handler itself is the entry point.

A class becomes runnable by implementing one interface. That is the whole access
rule: being constructible by the container is not enough, so nothing the
container happens to know about is reachable from the command line by accident.

```php
<?php // src/App/Job/RebuildSitemap.php

namespace App\Job;

use SummerCraft\Core\Cli\CliHandler;
use SummerCraft\Core\ComponentManaging\LifeCycle\RequestScopeComponent;

class RebuildSitemap implements CliHandler, RequestScopeComponent
{
    public function __construct(private SitemapWriter $writer) {}

    /** @param string[] $arguments */
    public function handle(array $arguments = []): void
    {
        $this->writer->rebuild(limit: (int)($arguments[0] ?? 100));
    }
}
```

Register the route once, in a config loader. The framework registers nothing on
its own — without this, no class is reachable this way:

```php
use SummerCraft\Core\Routing\RouterConfig;
use SummerCraft\Core\Routing\RoutingPattern;

$this->componentHolder->get(RouterConfig::class, null)->addPattern(
    RoutingPattern::cliHandler()
        ->forUriPatterns(['/handle/(:all)'])
        ->forMethod('CLI')
);
```

```bash
php bin/cli.php handle App/Job/RebuildSitemap        # handle([])
php bin/cli.php handle App/Job/RebuildSitemap 500    # handle(['500'])
```

Notes:

- **Keep `->forMethod('CLI')`.** Without it the same pattern answers ordinary
  HTTP requests, and every handler in the application becomes a public,
  unauthenticated URL.
- Namespaces are written with `/`, not `\` — a backslash is not in
  `RequestConfig::$permittedUriChars` and the segment would be rejected before
  routing runs.
- Where the class name ends and the arguments begin is decided by asking the
  autoloader: the longest leading run of segments naming an existing class wins,
  the rest is passed to `handle()`.
- A class that exists but does not implement `CliHandler` fails loudly with a
  `RuntimeException` rather than a 404 that would read like a typo.

## Class loading without composer

Composer's optimized autoloader is the default and the recommendation. The
framework also ships its own, for the case the rest of this project is built
around: running with dependencies composer did not install.

It maps namespace prefixes to directories, caches every class it resolves, and
is **prepended** before composer's — so it answers first, composer picks up
whatever it does not map, and it can be added or removed without touching
anything else:

```php
return AppBootstrap::create($basePath, AppConfigLoader::class)
    ->withBuiltinAutoloader([
        'App'               => 'src/app',
        'SummerCraft\\Core' => '../summer-craft-core/src/SummerCraft/Core',
    ]);
```

Paths are relative to `basePath`, so packages checked out next to the
application work as well as ones inside it. The cache is written to
`storage/framework/cache/` at the end of each run.

Map everything, including the PSR interface packages, and composer's autoloader
answers nothing at runtime:

```php
'Psr\\Log'           => 'vendor/psr/log/src',
'Psr\\Http\\Message' => 'vendor/psr/http-message/src',
// same namespace, separate packages: no common prefix, one entry per class
'Psr\\Http\\Server\\RequestHandlerInterface' => 'vendor/psr/http-server-handler/src/RequestHandlerInterface',
'Psr\\Http\\Server\\MiddlewareInterface'     => 'vendor/psr/http-server-middleware/src/MiddlewareInterface',
```

What this does not do is replace composer for *installing* third-party code —
the PSR interfaces still have to be on disk, whether composer put them there or
you vendored them by hand.

Map only part of it and the rest still works: unmapped classes reach composer a
step later. Those misses are silent by design. `logMisses: true` turns on a
per-miss log while you work out why a class of your own is not found — it
writes on every miss, so turn it back off.

## PSR-7

The framework ships its own PSR-7 implementation (`SummerCraft\Core\Http`:
`ServerRequest`, `Response`, `Stream`, `Uri`, `UploadedFile`) plus a bridge into
the native request/response cycle. The native interfaces stay untouched —
existing controllers keep working; new code can opt in per action:

```php
use Psr\Http\Message\ServerRequestInterface;
use SummerCraft\Core\Http\Response;

class ApiController implements RequestScopeComponent
{
    public function __construct(private ServerRequestInterface $request) {}

    public function searchAction(): Response
    {
        return Response::json([
            'query' => $this->request->getQueryParams()['q'] ?? '',
        ]);
    }
}
```

- `ServerRequestInterface` is registered in the container (request-scoped,
  built from superglobals by `ServerRequestFactory`).
- An action may return any `Psr\Http\Message\ResponseInterface` — the router
  emits it into the native response pipeline via `NativeResponseEmitter`.
- `Response::html()`, `Response::json()`, `Response::redirect()` are
  convenience factories.

### PSR-17, and being driven by something other than a SAPI

`Psr17Factory` implements the PSR-17 factories over those same classes:
`ServerRequestFactoryInterface`, `ResponseFactoryInterface`,
`StreamFactoryInterface`, `UploadedFileFactoryInterface`, `UriFactoryInterface`.

That is what anything wanting to hand the application a request — rather than
leaving it to read the superglobals — builds the request with:

```php
$factory = new Psr17Factory();
$app = AppBootstrap::create($basePath, MyConfigLoader::class)->boot();

while ($request = $runtime->waitRequest($factory)) {
    $runtime->respond($app->run($request));
}
```

`run()` always answers with a `ResponseInterface`, including for a failure it
could not handle, so a loop like this always has something to send back.

**Long-lived processes: what is true today.** The framework runs under FPM and
CLI; workers are a goal rather than a promise, and the parts that would be
expensive to retrofit are already in place — the request is a PSR-7 object,
superglobals are read in one place, per-request objects have their own scope and
are destroyed with it, and the benchmark holder is reset when a request ends. What
is not here is an integration with a specific runtime, and long-lived resources
have only been worked through for the cron worker (reconnect on a dropped MySQL
connection, signal handling), not proven under HTTP.

## Neighbouring packages

Each layer may use the one below it and never the one above:

- [summer-craft-service-hub](../summer-craft-service-hub) — database, logging, mail,
  HTTP client, files. Interfaces with default implementations, wired by the
  application.
- [summer-craft-skeleton](../summer-craft-skeleton) — `create-project` starting
  point, with entry points and a Docker stack already in place.

During development the packages are consumed as composer **path repositories**, so
the checkouts have to sit side by side in one parent directory.

## Testing

Everything runs in docker, no local PHP needed:

```bash
make test                 # PHPUnit on the latest PHP (8.5)
make test PHP=8.2         # a specific version
make test-all             # full matrix: PHP 8.4 – 8.5
make stan                 # phpstan static analysis (level 5)
make test ARGS="--filter RouterTest"
```

## License

MIT
