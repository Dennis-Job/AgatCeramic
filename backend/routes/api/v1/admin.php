<?php

use App\Http\Controllers\Api\V1\Admin\AdminUserController;
use App\Http\Controllers\Api\V1\Admin\AuditLogController;
use App\Http\Controllers\Api\V1\Admin\CategoryController;
use App\Http\Controllers\Api\V1\Admin\PermissionController;
use App\Http\Controllers\Api\V1\Admin\RoleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin API v1 Routes
|--------------------------------------------------------------------------
|
| This file is loaded inside the auth:sanctum and active_admin route group.
| Authorization policies for individual administrative operations are added
| in TASK-024.
|
*/

Route::get('users/roles', [AdminUserController::class, 'roles'])->name('users.roles');
Route::apiResource('users', AdminUserController::class);
Route::get('roles/permissions', [RoleController::class, 'permissions'])->name('roles.permissions');
Route::apiResource('roles', RoleController::class);
Route::apiResource('permissions', PermissionController::class)->only(['index', 'show']);
Route::apiResource('audit-logs', AuditLogController::class)->only(['index', 'show']);
Route::get('categories/tree', [CategoryController::class, 'tree'])->name('categories.tree');
Route::apiResource('categories', CategoryController::class);
