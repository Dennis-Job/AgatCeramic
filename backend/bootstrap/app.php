<?php

use App\Http\Middleware\EnsureActiveAdmin;
use App\Http\Responses\ApiErrorResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->throttleApi('api');
        $middleware->alias([
            'active_admin' => EnsureActiveAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $isApiV1Request = static fn (Request $request): bool => $request->is('api/v1')
            || $request->is('api/v1/*');

        $exceptions->render(function (ValidationException $exception, Request $request) use ($isApiV1Request) {
            if (! $isApiV1Request($request)) {
                return null;
            }

            return ApiErrorResponse::make(
                'validation_failed',
                'Переданные данные не прошли проверку.',
                $exception->status,
                $exception->errors(),
            );
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) use ($isApiV1Request) {
            return $isApiV1Request($request) ? ApiErrorResponse::fromStatus(401) : null;
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) use ($isApiV1Request) {
            return $isApiV1Request($request) ? ApiErrorResponse::fromStatus(403) : null;
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) use ($isApiV1Request) {
            return $isApiV1Request($request)
                ? ApiErrorResponse::fromStatus($exception->getStatusCode())
                : null;
        });

        $exceptions->render(function (Throwable $exception, Request $request) use ($isApiV1Request) {
            return $isApiV1Request($request)
                ? ApiErrorResponse::make('internal_server_error', 'Произошла непредвиденная ошибка.', 500)
                : null;
        });
    })->create();
