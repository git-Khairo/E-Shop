<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| React SPA
|--------------------------------------------------------------------------
| Every non-API URL is served by the React single-page application.
| React Router owns client-side routing (see resources/js/App.jsx).
|
| The actual REST API lives at /api/v1/* (see routes/api.php).
*/
Route::view('/{any?}', 'spa')
    ->where('any', '^(?!api).*$')
    ->name('spa');
