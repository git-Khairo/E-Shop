<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\ProductController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| V1 API
|--------------------------------------------------------------------------
| All endpoints are JSON. Auth is Sanctum Bearer token. Rate-limiting groups:
|   - "api"     : 60 req/min per IP
|   - "checkout": 10 req/min per user  (explained in DOCUMENTATION.md)
*/

Route::prefix('v1')->group(function () {

    // Public
    Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:api');
    Route::post('auth/login',    [AuthController::class, 'login'])->middleware('throttle:api');

    Route::get('products',     [ProductController::class, 'index'])->middleware('throttle:api');
    Route::get('products/{id}', [ProductController::class, 'show'])->middleware('throttle:api');

    Route::get('categories', [CategoryController::class, 'index'])->middleware('throttle:api');

    Route::post('contact', [ContactController::class, 'store'])->middleware('throttle:api');

    // Authenticated
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
    });

    // Checkout has its own tighter rate limit and per-user key.
    Route::middleware(['auth:sanctum', 'throttle:checkout'])->group(function () {
        Route::post('checkout', [OrderController::class, 'checkout']);
    });
});
