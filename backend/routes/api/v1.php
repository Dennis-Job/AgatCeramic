<?php

use App\Http\Controllers\Api\V1\Admin\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', static fn (): array => [
    'version' => 'v1',
])->name('index');

require base_path('routes/api/v1/public.php');

Route::prefix('admin')
    ->as('admin.')
    ->group(function (): void {
        Route::prefix('auth')
            ->as('auth.')
            ->group(function (): void {
                Route::post('login', [AuthController::class, 'login'])
                    ->middleware('throttle:login')
                    ->name('login');
            });

        Route::middleware(['auth:sanctum', 'active_admin'])
            ->group(function (): void {
                Route::prefix('auth')
                    ->as('auth.')
                    ->group(function (): void {
                        Route::get('me', [AuthController::class, 'me'])->name('me');
                        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
                    });

                require base_path('routes/api/v1/admin.php');
            });
    });
