<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Laravel registers this file with the /api prefix. All application API
| routes therefore live below /api/v1. Keep public and administrative route
| declarations in their own files to make their security boundaries explicit.
|
*/

Route::prefix('v1')
    ->as('api.v1.')
    ->group(base_path('routes/api/v1.php'));
