<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\CategoriesController;
use App\Http\Controllers\Dashboardcontroller;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;

Route::view('/', 'pages.home')->name('home');

Route::middleware('guest')->group(function() {
    Route::view('/login', 'pages.Login')->name('login');
    Route::post('/login', [AuthController::class, 'login']);

    Route::view('/register', 'pages.register')->name('register');
    Route::post('/register', [AuthController::class, 'register']);

});

Route::view('/about', 'pages.about')->name('about');
Route::view('/contact', 'pages.contact')->name('contact');
Route::get('/products', [CategoriesController::class, 'index'])->name('products');
Route::post('/products', [CategoriesController::class, 'show'])->name('showProducts');



Route::middleware('auth')->group(function() {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [Dashboardcontroller::class, 'index'])->name('dashboard');
    Route::get('/cart', [CartController::class, 'viewCart'])->name('cart');
    Route::post('/cart', [CartController::class, 'AddtoCart'])->name('AddtoCart');
    Route::post('/checkout', [CartController::class, 'saveToDatabase'])->name('saveToDatabase');
    Route::delete('/cart/{product_id}', [CartController::class, 'removeFromCart'])->name('removeFromCart');
    Route::patch('/cart/increase/{product_id}', [CartController::class, 'increaseQuantity'])->name('cartIncrease');
    Route::patch('/cart/decrease/{product_id}', [CartController::class, 'decreaseQuantity'])->name('cartDecrease');
});

Route::middleware('App\Http\Middleware\RoleMiddleware:admin')->group(function() {
    Route::get('/AdminPanel', [AdminController::class, 'index'])->name('AdminPanel');
});
