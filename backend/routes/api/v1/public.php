<?php

use App\Http\Controllers\Api\V1\CartController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public API v1 Routes
|--------------------------------------------------------------------------
|
| Public storefront endpoints never require an administrator session.
|
*/

Route::get('cart', [CartController::class, 'show'])->name('cart.show');
