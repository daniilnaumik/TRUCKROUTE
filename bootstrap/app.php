<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sanctum SPA stateful middleware включаем только если фронт-SPA
        // на одном домене. Для мобилки и токенов он не нужен.
        $middleware->statefulApi();

        // RBAC alias: ->middleware('role:provider,admin') — нужна одна из ролей.
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,
            'track.activity' => \App\Http\Middleware\TrackUserActivity::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Все исключения, прилетевшие на /api/*, отвечают JSON-ом единого формата.
        $exceptions->shouldRenderJsonWhen(function (Request $request, \Throwable $e): bool {
            return $request->is('api/*') || $request->expectsJson();
        });

        $exceptions->render(function (\Throwable $e, Request $request) {
            if (!($request->is('api/*') || $request->expectsJson())) {
                return null; // оставляем дефолтный HTML рендер для веб-страниц
            }

            if ($e instanceof ValidationException) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], $e->status);
            }

            if ($e instanceof AuthenticationException) {
                return response()->json([
                    'message' => 'Не авторизован.',
                ], 401);
            }

            if ($e instanceof NotFoundHttpException) {
                return response()->json([
                    'message' => 'Ресурс не найден.',
                ], 404);
            }

            if ($e instanceof HttpExceptionInterface) {
                return response()->json([
                    'message' => $e->getMessage() ?: 'Ошибка запроса.',
                ], $e->getStatusCode());
            }

            return response()->json([
                'message' => config('app.debug') ? $e->getMessage() : 'Внутренняя ошибка сервера.',
            ], 500);
        });
    })->create();
