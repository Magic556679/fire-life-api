<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Middleware\TrustProxies;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(TrustProxies::class, [
            // 您的 Nginx 代理是通過本地連線到 Laravel 的
            'proxies' => [
                '127.0.0.1',
                '::1',
            ],
            // 確保 Laravel 知道要使用 X-Forwarded-Proto
            'headers' => Request::HEADER_X_FORWARDED_FOR |
                Request::HEADER_X_FORWARDED_HOST |
                Request::HEADER_X_FORWARDED_PORT |
                Request::HEADER_X_FORWARDED_PROTO,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // 處理認證錯誤
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => '需要認證才能存取此資源',
                ], 401);
            }
        });

        // 處理模型找不到的錯誤
        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => '資源不存在',
                ], 404);
            }
        });

        // 處理 404 錯誤
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => '找不到請求的資源',
                ], 404);
            }
        });

        // 處理驗證錯誤
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => '驗證失敗',
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        // 處理其他一般錯誤
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*')) {
                // 在生產環境中不要顯示詳細錯誤訊息
                $message = app()->environment('production') ? '伺服器發生錯誤' : $e->getMessage();

                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], 500);
            }
        });
    })->create();
