<?php

use Illuminate\Support\Facades\Route;

Route::get('/', static fn (): array => [
    'version' => 'v1',
])->name('index');

require base_path('routes/api/v1/public.php');

Route::prefix('admin')
    ->as('admin.')
    ->group(base_path('routes/api/v1/admin.php'));
