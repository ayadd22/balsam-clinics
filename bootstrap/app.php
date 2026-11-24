<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use App\Http\Middleware\EnsureUserHasRole;
use App\Helpers\ApiResponse;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        $exceptions->shouldRenderJsonWhen(
            fn ($request, $exception) => $request->is('api/*')
        );

        $exceptions->render(function (
            AuthenticationException $exception,
            $request
        ) {
            return ApiResponse::error(
                'Unauthenticated.',
                401
            );
        });

        $exceptions->render(function (
            AuthorizationException $exception,
            $request
        ) {
            return ApiResponse::error(
                'This action is unauthorized.',
                403
            );
        });

        $exceptions->render(function (
            ModelNotFoundException $exception,
            $request
        ) {
            return ApiResponse::error(
                'Resource not found.',
                404
            );
        });

        $exceptions->render(function (
            NotFoundHttpException $exception,
            $request
        ) {
            return ApiResponse::error(
                'Resource not found.',
                404
            );
        });

        $exceptions->render(function (
            ValidationException $exception,
            $request
        ) {
            return ApiResponse::error(
                'The given data was invalid.',
                422,
                $exception->errors()
            );
        });

        $exceptions->render(function (
            HttpExceptionInterface $exception,
            $request
        ) {
            return ApiResponse::error(
                $exception->getMessage() ?: 'Request failed.',
                $exception->getStatusCode()
            );
        });
    })
    ->create();
