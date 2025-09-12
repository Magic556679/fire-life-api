<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/reset-password/{token}', function ($token) {
    return response()->json([
        'message' => '這裡應該導向前端的 ResetPassword 頁面',
        'token' => $token,
    ]);
})->name('password.reset');