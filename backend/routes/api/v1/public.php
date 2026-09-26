<?php

use App\Http\Controllers\Api\V1\BannerController;
use App\Http\Controllers\Api\V1\CallbackRequestController;
use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\CartItemController;
use App\Http\Controllers\Api\V1\EmailRequestController;
use App\Http\Controllers\Api\V1\LegalDocumentController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PageController;
use App\Http\Controllers\Api\V1\PartnerRequestController;
use App\Http\Controllers\Api\V1\SiteSettingController;
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
Route::get('site-settings', [SiteSettingController::class, 'show'])->name('site-settings.show');
Route::get('pages', [PageController::class, 'index'])->name('pages.index');
Route::get('banners', [BannerController::class, 'index'])->name('banners.index');
Route::get('pages/{slug}', [PageController::class, 'show'])->name('pages.show');
Route::get('legal-documents/{type}', [LegalDocumentController::class, 'show'])->name('legal-documents.show');
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
Route::post('partner-requests', [PartnerRequestController::class, 'store'])
    ->middleware('throttle:partner-request')
    ->name('partner-requests.store');
