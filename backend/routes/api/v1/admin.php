<?php

use App\Http\Controllers\Api\V1\Admin\AdminUserController;
use App\Http\Controllers\Api\V1\Admin\AttributeController;
use App\Http\Controllers\Api\V1\Admin\AttributeGroupController;
use App\Http\Controllers\Api\V1\Admin\AuditLogController;
use App\Http\Controllers\Api\V1\Admin\BrandController;
use App\Http\Controllers\Api\V1\Admin\CategoryAttributeController;
use App\Http\Controllers\Api\V1\Admin\CategoryController;
use App\Http\Controllers\Api\V1\Admin\PermissionController;
use App\Http\Controllers\Api\V1\Admin\ProductAttributeValueController;
use App\Http\Controllers\Api\V1\Admin\ProductController;
use App\Http\Controllers\Api\V1\Admin\ProductExportController;
use App\Http\Controllers\Api\V1\Admin\ProductGroupController;
use App\Http\Controllers\Api\V1\Admin\ProductImageController;
use App\Http\Controllers\Api\V1\Admin\ProductRelationController;
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
Route::get('products/export', ProductExportController::class)->name('products.export');
Route::apiResource('products', ProductController::class);
Route::apiResource('product-groups', ProductGroupController::class);
Route::get('products/{product}/attributes', [ProductAttributeValueController::class, 'index'])->name('products.attributes.index');
Route::put('products/{product}/attributes', [ProductAttributeValueController::class, 'replace'])->name('products.attributes.replace');
Route::get('products/{product}/images', [ProductImageController::class, 'index'])->name('products.images.index');
Route::post('products/{product}/images', [ProductImageController::class, 'store'])->name('products.images.store');
Route::patch('products/{product}/images/{image}', [ProductImageController::class, 'update'])->name('products.images.update');
Route::delete('products/{product}/images/{image}', [ProductImageController::class, 'destroy'])->name('products.images.destroy');
Route::get('products/{product}/relations', [ProductRelationController::class, 'index'])->name('products.relations.index');
Route::get('products/{product}/relation-candidates', [ProductRelationController::class, 'candidates'])->name('products.relations.candidates');
Route::put('products/{product}/relations', [ProductRelationController::class, 'replace'])->name('products.relations.replace');
