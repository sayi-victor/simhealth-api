<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| This repository serves JSON only. The Vue SPA lives in its own repository
| (simhealth) and talks to the /api routes defined in routes/api.php.
|
*/

Route::get('/', fn () => response()->json([
    'data' => [
        'service' => 'simhealth-api',
        'health' => url('/api/health'),
    ],
]));
