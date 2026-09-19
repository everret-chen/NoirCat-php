<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\ErrorCode;
use App\Http\Responses\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

/**
 * Renders failures of API requests with the { code, message, data } envelope.
 *
 * Web requests return null so Laravel keeps rendering its own error pages.
 */
class ApiExceptionRenderer
{
    public function render(Throwable $exception, Request $request): ?JsonResponse
    {
        if (! $this->expectsEnvelope($request)) {
            return null;
        }

        return match (true) {
            $exception instanceof BusinessException => ApiResponse::error(
                $exception->errorCode,
                $exception->getMessage(),
                $exception->httpStatus(),
                $exception->data,
            ),
            $exception instanceof ValidationException => ApiResponse::error(
                ErrorCode::VALIDATION_FAILED,
                $this->validationMessage($exception),
                422,
                ['errors' => $exception->errors()],
            ),
            $exception instanceof AuthenticationException => ApiResponse::error(ErrorCode::UNAUTHENTICATED),
            $exception instanceof AuthorizationException,
            $exception instanceof AccessDeniedHttpException => ApiResponse::error(ErrorCode::FORBIDDEN),
            // A missing model is a resource problem, an unmatched path is a routing problem.
            $exception instanceof NotFoundHttpException
                && $exception->getPrevious() instanceof ModelNotFoundException => ApiResponse::error(ErrorCode::RESOURCE_NOT_FOUND),
            $exception instanceof NotFoundHttpException => ApiResponse::error(ErrorCode::ROUTE_NOT_FOUND),
            $exception instanceof MethodNotAllowedHttpException => ApiResponse::error(ErrorCode::METHOD_NOT_ALLOWED),
            $exception instanceof TooManyRequestsHttpException => ApiResponse::error(ErrorCode::RATE_LIMITED),
            $exception instanceof HttpExceptionInterface => ApiResponse::error(ErrorCode::fromHttpStatus($exception->getStatusCode())),
            default => $this->systemError($exception),
        };
    }

    /**
     * Internal failures keep their details out of the response unless
     * APP_DEBUG is on, so production never leaks stack information.
     */
    private function systemError(Throwable $exception): JsonResponse
    {
        $debug = (bool) config('app.debug');

        return ApiResponse::error(
            ErrorCode::SYSTEM_ERROR,
            $debug ? $exception->getMessage() : ErrorCode::SYSTEM_ERROR->message(),
            500,
            $debug ? [
                'exception' => $exception::class,
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ] : null,
        );
    }

    private function validationMessage(ValidationException $exception): string
    {
        $first = collect($exception->errors())->flatten()->first();

        return is_string($first) && $first !== ''
            ? $first
            : ErrorCode::VALIDATION_FAILED->message();
    }

    private function expectsEnvelope(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }
}
