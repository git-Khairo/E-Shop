<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\MonitorController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\WalletController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:api');
    Route::post('auth/login',    [AuthController::class, 'login'])->middleware('throttle:api');

    Route::get('products',     [ProductController::class, 'index'])->middleware('throttle:api');
    Route::get('products/{id}', [ProductController::class, 'show'])->middleware('throttle:api');

    Route::get('categories', [CategoryController::class, 'index'])->middleware('throttle:api');

    Route::post('contact', [ContactController::class, 'store'])->middleware('throttle:api');

    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::get('auth/me',       [AuthController::class, 'me']);
        Route::post('auth/logout',  [AuthController::class, 'logout']);

        Route::get('cart',                 [CartController::class, 'index']);
        Route::post('cart',                [CartController::class, 'add']);
        Route::patch('cart/{productId}',   [CartController::class, 'update']);
        Route::delete('cart',              [CartController::class, 'clear']);
        Route::delete('cart/{productId}',  [CartController::class, 'remove']);

        Route::get('orders',           [OrderController::class, 'index']);
        Route::get('orders/{orderId}', [OrderController::class, 'show']);

        Route::get('wallet',               [WalletController::class, 'show']);
        Route::post('wallet/credit',       [WalletController::class, 'credit']);
        Route::post('wallet/debit',        [WalletController::class, 'debit']);
        Route::post('wallet/transfer',     [WalletController::class, 'transfer']);
        Route::get('wallet/transactions',  [WalletController::class, 'transactions']);
    });

    Route::get('monitor/dashboard', [MonitorController::class, 'dashboard']);

    Route::middleware(['auth:sanctum', 'concurrency:checkout,10'])->group(function () {
        Route::post('checkout', [OrderController::class, 'checkout']);
    });
});
