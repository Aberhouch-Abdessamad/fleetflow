<?php

namespace App\Exceptions;

use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Exceptions\UnauthorizedException as PermissionDenied;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

/** Converts every exception on /api/* into the standard error envelope. Never leaks internals. */
final class ApiExceptionRenderer
{
    public static function shouldHandle(Request $request): bool
    {
        return $request->is('api/*');
    }

    public static function render(Throwable $e, Request $request): ?JsonResponse
    {
        if (! self::shouldHandle($request) && ! $request->expectsJson()) {
            return null;
        }

        return match (true) {
            $e instanceof ValidationException => ApiResponse::error(
                $e->getMessage(), 422, 'validation_failed', $e->errors()
            ),
            $e instanceof DomainException => ApiResponse::error($e->getMessage(), $e->status, $e->errorCode),
            $e instanceof AuthenticationException => ApiResponse::error('Unauthenticated.', 401, 'unauthenticated'),
            $e instanceof AuthorizationException,
            $e instanceof PermissionDenied,
            $e instanceof AccessDeniedHttpException => ApiResponse::error(
                'You are not allowed to perform this action.', 403, 'forbidden'
            ),
            $e instanceof ModelNotFoundException,
            $e instanceof NotFoundHttpException => ApiResponse::error('Resource not found.', 404, 'not_found'),
            $e instanceof TokenMismatchException => ApiResponse::error('Session expired. Refresh and try again.', 419, 'csrf_mismatch'),
            $e instanceof TooManyRequestsHttpException => ApiResponse::error('Too many requests. Slow down.', 429, 'rate_limited'),
            $e instanceof HttpExceptionInterface => ApiResponse::error(
                $e->getMessage() ?: 'Request failed.', $e->getStatusCode(), 'http_error'
            ),
            default => ApiResponse::error(
                config('app.debug') ? $e->getMessage() : 'Something went wrong. Please try again.',
                500,
                'server_error'
            ),
        };
    }
}
