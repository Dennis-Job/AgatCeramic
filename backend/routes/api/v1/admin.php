<?php

use App\Http\Controllers\Api\V1\Admin\AdminUserController;
use App\Http\Controllers\Api\V1\Admin\AttributeController;
use App\Http\Controllers\Api\V1\Admin\AttributeGroupController;
use App\Http\Controllers\Api\V1\Admin\AuditLogController;
use App\Http\Controllers\Api\V1\Admin\BrandController;
use App\Http\Controllers\Api\V1\Admin\CategoryAttributeController;
use App\Http\Controllers\Api\V1\Admin\CategoryController;
use App\Http\Controllers\Api\V1\Admin\PermissionController;
use App\Http\Controllers\Api\V1\Admin\ProductController;
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
Route::get('categories/{category}/attributes', [CategoryAttributeController::class, 'index'])->name('categories.attributes.index');
Route::put('categories/{category}/attributes', [CategoryAttributeController::class, 'replace'])->name('categories.attributes.replace');
Route::get('categories/{category}/attribute-groups', [CategoryAttributeController::class, 'groups'])->name('categories.attribute-groups.index');
Route::put('categories/{category}/attribute-groups', [CategoryAttributeController::class, 'replaceGroups'])->name('categories.attribute-groups.replace');
Route::apiResource('categories', CategoryController::class);
Route::apiResource('attribute-groups', AttributeGroupController::class);
Route::apiResource('attributes', AttributeController::class);
Route::apiResource('brands', BrandController::class);
Route::apiResource('products', ProductController::class);
