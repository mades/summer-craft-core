<?php

namespace SummerCraft\Core\Response;

use Psr\Http\Message\ServerRequestInterface;
use SummerCraft\Core\ComponentManaging\RequestScope;
use SummerCraft\Core\EventDispatcher\EventDispatcher;
use SummerCraft\Core\EventDispatcher\SimpleEvent;
use SummerCraft\Core\ExceptionProcessing\ExceptionProcessor;
use SummerCraft\Core\ExceptionProcessing\ThrowableContext;
use SummerCraft\Core\Http\ResponseAccumulator;
use SummerCraft\Core\Http\StatusCode;

class SpecificResponseHandler
{
    public function __construct(
        private RequestScope $requestScope,
        private EventDispatcher $eventDispatcher,
        private ServerRequestInterface $request,
        private ResponseAccumulator $response,
        private ThrowableContext $throwableContext,
    ) { }

    public function errorForbidden(string $message = ''): void
    {
        $this->eventDispatcher->fire(new SimpleEvent('specific_response_shown', ['message' => 'Page '. StatusCode::FORBIDDEN .' showed with uri ['. $this->request->getUri() .']']));
        $this->response->setStatus(StatusCode::FORBIDDEN);
        $this->response->append('<h1>403 Forbidden</h1>');
        $this->response->append('<br/><br/>Additional Message: ' . $message);
    }

    public function errorBadRequest(): void
    {
        $this->eventDispatcher->fire(new SimpleEvent('specific_response_shown', ['message' => 'Page '. StatusCode::BAD_REQUEST .' showed with uri ['. $this->request->getUri() .']']));

        $this->response->setStatus(StatusCode::BAD_REQUEST);
        $this->response->append('<h1>400 Bad Request</h1>');
    }

    public function errorNotFound(): void
    {
        $this->eventDispatcher->fire(new SimpleEvent('specific_response_shown', ['message' => 'Page '. StatusCode::NOT_FOUND .' showed with uri ['. $this->request->getUri() .']']));

        $this->response->setStatus(StatusCode::NOT_FOUND);
        $this->response->append('<h1>404 Not found</h1>');
    }

    public function errorServerError(): void
    {
        $this->response->setStatus(StatusCode::INTERNAL_SERVER_ERROR);
        $responseString = ExceptionProcessor::defaultProcessExceptionToString(
            $this->throwableContext->getThrowable(),
            $this->requestScope
        );

        $this->eventDispatcher->fire(new SimpleEvent('specific_response_shown', ['message' => 'Page '. StatusCode::INTERNAL_SERVER_ERROR .' showed with uri ['. $this->request->getUri() .'] [' . $responseString . ']']));

        if (empty($responseString)) {
            $responseString = 'Internal server error 500.';
        }
        $this->response->append($responseString);
    }
}
