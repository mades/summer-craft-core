<?php

namespace SummerCraft\Core;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use SummerCraft\Core\ComponentManaging\ComponentHolder;
use SummerCraft\Core\ComponentManaging\Config\Config;
use SummerCraft\Core\ComponentManaging\RequestScope;
use SummerCraft\Core\ConfigLoader\CoreConfigLoader;
use SummerCraft\Core\Context\ApplicationContext;
use SummerCraft\Core\EventDispatcher\EventDispatcher;
use SummerCraft\Core\EventDispatcher\SimpleEvent;
use SummerCraft\Core\ExceptionProcessing\ExceptionProcessingConfig;
use SummerCraft\Core\ExceptionProcessing\ExceptionProcessor;
use SummerCraft\Core\Http\ProfilerDecorator;
use SummerCraft\Core\Http\Response;
use SummerCraft\Core\Http\ResponseAccumulator;
use SummerCraft\Core\Http\SapiEmitter;
use SummerCraft\Core\Http\StatusCode;
use SummerCraft\Core\Http\ServerRequestFactory;
use SummerCraft\Core\Http\Stream;
use SummerCraft\Core\Request\RequestIdentity;
use SummerCraft\Core\Routing\Router;
use Throwable;

final class Application
{
    private static Application $instance;

    private ApplicationContext $applicationContext;

    private ComponentHolder $componentHolder;

    public static function getInstance(): Application
    {
        if (!isset(self::$instance)) {
            throw new \RuntimeException("Application not created. It should be created before usage.");
        }
        return self::$instance;
    }

    public static function hasInstance(): bool
    {
        return isset(self::$instance);
    }

    public static function configureErrorHandlers(): void
    {
        ExceptionProcessor::configureDefaultHandlers();
    }

    public static function create(ApplicationContext $applicationContext): Application
    {
        self::$instance = new self();
        self::$instance->init($applicationContext);
        return self::$instance;
    }

    public function init(ApplicationContext $applicationContext): void
    {
        $benchmark = BenchmarkHolder::getInstance();

        $this->initEnvironment();

        $config = new Config();
        $this->applicationContext = $applicationContext;
        $this->componentHolder = new ComponentHolder($config);
        $this->componentHolder->set(ComponentHolder::class, null, $this->componentHolder);
        $this->componentHolder->set(Application::class, null, $this);
        $this->componentHolder->set(Config::class, null, $config);
        $this->componentHolder->set(ApplicationContext::class, null, $applicationContext);
        $this->componentHolder->set(BenchmarkHolder::class, null, $benchmark);

        $environmentConfigLoaderClass = $applicationContext->getConfigLoader();
        /** @var CoreConfigLoader $configLoader */
        $configLoader = new $environmentConfigLoaderClass($this->componentHolder, $this->applicationContext);
        $configLoader->load();
        $this->initExceptionProcessing();
        $configLoader->initialize();
    }

    /**
     * Charset environment setup (moved from the removed DefaultRequest).
     */
    private function initEnvironment(): void
    {
        $charset = 'UTF-8';
        ini_set('default_charset', $charset);

        if (extension_loaded('mbstring')) {
            // required for mb_convert_encoding() to strip invalid characters
            mb_substitute_character('none');
        } elseif (!extension_loaded('iconv')) {
            throw new RuntimeException('Server not support iconv or mbstring');
        }
        ini_set('php.internal_encoding', $charset);
    }

    private function initExceptionProcessing(): void
    {
        $config = $this->componentHolder->get(ExceptionProcessingConfig::class, null);
        ExceptionProcessor::initErrorConfiguration($this->applicationContext, $config);
    }

    public function getContext(): ApplicationContext
    {
        return $this->applicationContext;
    }

    /**
     * Get shared or no-shared component by key
     * @template T of object
     * @param class-string<T> $componentName Component name or className
     * @param RequestIdentity|null $requestIdentity Identity of current request scope
     * @return T
     */
    public function get(string $componentName, ?RequestIdentity $requestIdentity = null): object
    {
        return $this->componentHolder->get($componentName, $requestIdentity);
    }

    /**
     * Run the application for one request. Always answers with a response — a failure
     * it could not handle comes back as a 500, so a caller serving many requests has
     * something to send either way.
     */
    public function run(?ServerRequestInterface $request = null): ResponseInterface
    {
        $requestScope = null;
        try {
            $requestScope = new RequestScope($this->componentHolder);
            $this->componentHolder->set(RequestScope::class, $requestScope->getIdentity(), $requestScope);

            if ($request === null) {
                $request = ServerRequestFactory::fromGlobals();
            }
            $this->componentHolder->set(ServerRequestInterface::class, $requestScope->getIdentity(), $request);

            // after the request is in scope, and carrying it: a subscriber fired before
            // that could reach the request through neither the payload nor the container,
            // and the one subscriber this ever had read $_SERVER instead — which is the
            // state a worker runtime must not be reading
            $eventDispatcher = $requestScope->get(EventDispatcher::class);
            $eventDispatcher->fire(new SimpleEvent('request.start', ['request' => $request]));

            $router = $requestScope->get(Router::class);
            $router->route($requestScope);

            $response = $requestScope->get(ResponseAccumulator::class)->toResponse();

            $body = (string)$response->getBody();
            $decorated = $requestScope->get(ProfilerDecorator::class)->apply($body);
            if ($decorated !== $body) {
                $response = $response->withBody(Stream::create($decorated));
            }

            return $response;
        } catch (Throwable $ex) {
            // a Response rather than echo: printing here bypassed the emitter, so the
            // status stayed 200 while the body carried an error page, and the caller
            // got null with the output already gone. A worker has nothing to send back
            // at all in that shape.
            return Response::html(
                ExceptionProcessor::defaultProcessExceptionToString($ex, $requestScope),
                StatusCode::INTERNAL_SERVER_ERROR
            );
        } finally {
            if ($requestScope !== null) {
                $this->componentHolder->destroyScope($requestScope);
            }
            // at the end, not the start: APP_START is set when the holder is built,
            // during init(), so the first request legitimately measures the boot as
            // well. Resetting on the way in would throw that away; resetting on the
            // way out leaves the next request measuring only itself. The profiler has
            // already read the markers into the body by now.
            $this->componentHolder->get(BenchmarkHolder::class, null)->reset();
            // Application run with custom Autoloader, not composer
            if ($builtinAutoloader = $this->applicationContext->getBuiltinAutoloader()) {
                $builtinAutoloader->saveCache();
            }
        }
    }

    /**
     * Emit the response through the SAPI (status line, headers, body).
     */
    public function send(?ResponseInterface $response, ?ServerRequestInterface $request = null): void
    {
        if ($response === null) {
            return;
        }
        $isHeadRequest = $request !== null && strtoupper($request->getMethod()) === 'HEAD';
        SapiEmitter::emit($response, 'HTTP/' . $response->getProtocolVersion(), $isHeadRequest);
    }
}
