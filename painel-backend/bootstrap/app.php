<?php

use App\Exceptions\DomainException;
use App\Http\Middleware\EnsureChatAccessKey;
use App\Http\Middleware\EnsureIsActive;
use App\Http\Middleware\EnsureIsMaster;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'active' => EnsureIsActive::class,
            'master' => EnsureIsMaster::class,
            'chat.key' => EnsureChatAccessKey::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (DomainException $e) {
            return ApiResponse::error($e->errorCode(), $e->getMessage(), $e->status());
        });

        $exceptions->render(function (ValidationException $e) {
            return ApiResponse::error('VALIDATION_ERROR', $e->getMessage(), 422);
        });

        $exceptions->render(function (AuthenticationException $e) {
            return ApiResponse::error('UNAUTHENTICATED', 'Não autenticado.', 401);
        });

        $exceptions->render(function (AuthorizationException|AccessDeniedHttpException $e) {
            return ApiResponse::error('FORBIDDEN', $e->getMessage() ?: 'Ação não autorizada.', 403);
        });

        $exceptions->render(function (ThrottleRequestsException $e) {
            return ApiResponse::error('TOO_MANY_REQUESTS', 'Muitas tentativas. Aguarde um instante.', 429);
        });

        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $e) {
            return ApiResponse::error('NOT_FOUND', 'Recurso não encontrado.', 404);
        });
    })->create();
