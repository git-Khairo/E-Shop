<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::view('/', 'pages.home')->name('home');

Route::middleware('guest')->group(function() {
    Route::view('/login', 'pages.Login')->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    
    Route::view('/register', 'pages.register')->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

Route::middleware('auth')->group(function() {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
