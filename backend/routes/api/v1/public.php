<?php

use App\Http\Controllers\Api\V1\CallbackRequestController;
use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\CartItemController;
use App\Http\Controllers\Api\V1\EmailRequestController;
use App\Http\Controllers\Api\V1\OrderController;
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
Route::post('cart/items', [CartItemController::class, 'store'])->name('cart.items.store');
Route::patch('cart/items/{item}', [CartItemController::class, 'update'])->name('cart.items.update');
Route::delete('cart/items/{item}', [CartItemController::class, 'destroy'])->name('cart.items.destroy');
Route::post('orders', [OrderController::class, 'store'])->middleware('throttle:order-create')->name('orders.store');
Route::post('callback-requests', [CallbackRequestController::class, 'store'])
    ->middleware('throttle:callback-request')
    ->name('callback-requests.store');
Route::post('email-requests', [EmailRequestController::class, 'store'])
    ->middleware('throttle:email-request')
    ->name('email-requests.store');
