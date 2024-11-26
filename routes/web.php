<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\CategoriesController;
use App\Http\Controllers\Dashboardcontroller;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;

Route::view('/', 'pages.home')->name('home');

Route::resource('product',ProductController::class);

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
    Route::get('/dashboard', [Dashboardcontroller::class, 'viewUserOrders'])->name('dashboard');
    Route::post('/dashboard', [Dashboardcontroller::class, 'viewUserOrdersOption'])->name('option');
    Route::get('/dashboard/profile', [Dashboardcontroller::class, 'viewUserProfile'])->name('profile');
    Route::put('/dashboard/profile', [Dashboardcontroller::class, 'updateUserProfile'])->name('updateProfile');
    Route::delete('/dashboard/Order/{orderId}', [Dashboardcontroller::class, 'deleteOrder'])->name('deleteOrder');
    Route::get('/dashboard/Order/{orderId}', [Dashboardcontroller::class, 'orderDetails'])->name('orderDetails');
    Route::get('/cart', [CartController::class, 'viewCart'])->name('cart');
    Route::post('/cart', [CartController::class, 'AddtoCart'])->name('AddtoCart');
    Route::post('/checkout', [CartController::class, 'saveToDatabase'])->name('saveToDatabase');
    Route::delete('/cart/{product_id}', [CartController::class, 'removeFromCart'])->name('removeFromCart');
    Route::patch('/cart/increase/{product_id}', [CartController::class, 'increaseQuantity'])->name('cartIncrease');
    Route::patch('/cart/decrease/{product_id}', [CartController::class, 'decreaseQuantity'])->name('cartDecrease');
    Route::delete('/logout', [AuthController::class, 'logout'])->name('logout');
});

// Route::middleware('App\Http\Middleware\RoleMiddleware:admin')->group(function() {
    Route::get('/AdminPanel', [AdminController::class, 'viewAllOrders'])->name('AdminPanel');
    Route::view('/AdminPanel/Add-Product', 'pages.create')->name('addProduct');
    Route::get('/AdminPanel/products', [AdminController::class, 'viewAllProducts'])->name('AdminPanelProducts');
    Route::post('/AdminPanel/Confirm/{orderId}', [AdminController::class, 'confirmOrder'])->name('Confirm');
    Route::delete('/AdminPanel/Delete/{orderId}', [AdminController::class, 'deleteOrder'])->name('Delete');
// });
