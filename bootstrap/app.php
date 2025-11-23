<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Auth\AuthenticationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api([
            \App\Http\Middleware\SetLocale::class
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // 處理認證錯誤
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => __('system.authentication_required'),
                ], 401);
            }
        });

        // 處理模型找不到的錯誤
        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => __('system.resource_not_found'),
                ], 404);
            }
        });

        // 處理 404 錯誤
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => __('system.request_not_found'),
                ], 404);
            }
        });

        // 處理驗證錯誤
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => __('system.validation_failed'),
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        // 處理其他一般錯誤
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*')) {
                // 在生產環境中不要顯示詳細錯誤訊息
                $message = app()->environment('production')
                    ? __('system.server_error')
                    : $e->getMessage();

                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], 500);
            }
        });
    })->create();
