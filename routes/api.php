<?php

use App\Http\Controllers\Auth\PermissionController;
use App\Http\Controllers\Auth\RoleController;
use App\Http\Controllers\Auth\UserController;
use App\Http\Controllers\RegionController;
use App\Http\Controllers\SuperAdmin\MasterData\CategoryController;
use App\Http\Controllers\SuperAdmin\MasterData\ProductController;
use App\Http\Controllers\SuperAdmin\MasterData\WarehouseController;
use App\Http\Controllers\SuperAdmin\MasterData\SupplierController;
use App\Http\Controllers\SuperAdmin\MasterData\UnitController;
use App\Http\Controllers\SuperAdmin\PurchaseOrderController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('/permissions')->middleware(['auth:sanctum', 'role:super-admin'])->name('permission.')->group(function () {
    Route::get('', [PermissionController::class, 'index'])->middleware('permission:view_permission')->name('index');
    Route::post('/create', [PermissionController::class, 'store'])->middleware('permission:create_permission')->name('create');
    Route::put('/{permission}', [PermissionController::class, 'update'])->middleware('permission:edit_permission')->name('update');
    Route::delete('/{permission}', [PermissionController::class, 'destroy'])->middleware('permission:delete_permission')->name('delete');
});

Route::prefix('/roles')->middleware(['auth:sanctum', 'role:super-admin'])->name('role.')->group(function () {
    Route::get('', [RoleController::class, 'index'])->middleware('permission:view_role')->name('index');
    Route::post('/create', [RoleController::class, 'store'])->middleware('permission:create_role')->name('create');
    Route::put('/{role}', [RoleController::class, 'update'])->middleware('permission:edit_role')->name('update');
    Route::delete('/{role}', [RoleController::class, 'destroy'])->middleware('permission:delete_role')->name('delete');
    Route::post('/{role}/permissions', [RoleController::class, 'assignPermissions'])->middleware('permission:assign_permissions')->name('assignPermissions');
});

Route::prefix('/users')->middleware(['auth:sanctum', 'role:super-admin'])->name('user.')->group(function () {
    Route::get('', [UserController::class, 'index'])->middleware('permission:view_user')->name('index');
    Route::post('/create', [UserController::class, 'store'])->middleware('permission:create_user')->name('create');
    Route::put('/{user}', [UserController::class, 'update'])->middleware('permission:edit_user')->name('update');
});

Route::prefix('/regions')->group(function () {
    Route::get('/provinces', [RegionController::class, 'provinces']);
    Route::get('/regencies/{provinceId}', [RegionController::class, 'regencies']);
    Route::get('/districts/{regencyId}', [RegionController::class, 'districts']);
    Route::get('/villages/{districtId}', [RegionController::class, 'villages']);
});

Route::prefix('/warehouses')->middleware('auth:sanctum')->name('warehouse.')->group(function () {
    Route::get('', [WarehouseController::class, 'index'])->middleware('permission:view_warehouse')->name('index');
    Route::post('/create', [WarehouseController::class, 'store'])->middleware('permission:create_warehouse')->name('create');
    Route::get('/{warehouse}', [WarehouseController::class, 'show'])->middleware('permission:view_warehouse')->name('show');
    Route::put('/{warehouse}', [WarehouseController::class, 'update'])->middleware('permission:edit_warehouse')->name('update');
    Route::delete('/{warehouse}', [WarehouseController::class, 'destroy'])->middleware('permission:delete_warehouse')->name('delete');
});

Route::prefix('/suppliers')->middleware('auth:sanctum')->name('supplier.')->group(function () {
    Route::get('', [SupplierController::class, 'index'])->middleware('permission:view_supplier')->name('index');
    Route::post('/create', [SupplierController::class, 'store'])->middleware('permission:create_supplier')->name('create');
    Route::get('/{supplier}', [SupplierController::class, 'show'])->middleware('permission:view_supplier')->name('show');
    Route::put('/{supplier}', [SupplierController::class, 'update'])->middleware('permission:edit_supplier')->name('update');
    Route::delete('/{supplier}', [SupplierController::class, 'destroy'])->middleware('permission:delete_supplier')->name('delete');
    Route::get('/{supplier}/products', [SupplierController::class, 'getProductBySupplier'])->middleware('permission:view_products')->name('product.supplier');
});

Route::prefix('/categories')->middleware('auth:sanctum')->name('category.')->group(function () {
    Route::get('', [CategoryController::class, 'index'])->middleware('permission:view_category')->name('index');
    Route::post('/create', [CategoryController::class, 'store'])->middleware('permission:create_category')->name('create');
    Route::put('/{category}', [CategoryController::class, 'update'])->middleware('permission:edit_category')->name('update');
    Route::delete('/{category}', [CategoryController::class, 'destroy'])->middleware('permission:delete_category')->name('destroy');
});

Route::prefix('/units')->middleware('auth:sanctum')->name('unit.')->group(function () {
    Route::get('', [UnitController::class, 'index'])->middleware('permission:view_unit')->name('index');
    Route::post('/create', [UnitController::class, 'store'])->middleware('permission:create_unit')->name('create');
    Route::put('/{unit}', [UnitController::class, 'update'])->middleware('permission:edit_unit')->name('update');
    Route::delete('/{unit}', [UnitController::class, 'destroy'])->middleware('permission:delete_unit')->name('delete');
});

Route::prefix('/products')->middleware('auth:sanctum')->name('product.')->group(function () {
    Route::get('', [ProductController::class, 'index'])->middleware('permission:view_products')->name('index');
    Route::get('/trash', [ProductController::class, 'trashed'])->middleware('permission:restore_products')->name('trash');
    Route::post('/create', [ProductController::class, 'store'])->middleware('permission:create_products')->name('create');
    Route::get('/{product}', [ProductController::class, 'show'])->middleware('permission:view_products')->name('show');
    Route::put('/{product}', [ProductController::class, 'update'])->middleware('permission:edit_products')->name('update');
    Route::delete('/{product}/soft', [ProductController::class, 'destroy'])->middleware('permission:delete_products')->name('delete');
    Route::patch('/{product}/restore', [ProductController::class, 'restore'])->middleware('permission:restore_products')->name('restore')->withTrashed();
    Route::delete('/{product}/force', [ProductController::class, 'forceDestroy'])->middleware('permission:delete_products')->name('force')->withTrashed();
});

Route::prefix('/purchases')->middleware('auth:sanctum')->name('purchase.')->group(function () {
    Route::get('', [PurchaseOrderController::class, 'index'])->middleware('permission:view_purchase')->name('index');
    Route::get('/trash', [PurchaseOrderController::class, 'trashed'])->middleware('permission:restore_purchase')->name('trash');
    Route::post('/request', [PurchaseOrderController::class, 'request'])->middleware('permission:create_purchase')->name('create');
    Route::get('/{purchase}', [PurchaseOrderController::class, 'show'])->middleware('permission:view_purchase')->name('show');
    Route::put('/{purchase}/update', [PurchaseOrderController::class, 'update'])->middleware('permission:edit_purchase')->name('update');
    Route::patch('/{purchase}/status', [PurchaseOrderController::class, 'status'])->middleware('permission:confirm_purchase')->name('status');
    Route::delete('/{purchase}/soft', [PurchaseOrderController::class, 'destroy'])->middleware('permission:delete_purchase')->name('destroy');
    Route::patch('/{purchase}/restore', [PurchaseOrderController::class, 'restore'])->middleware('permission:restore_purchase')->name('restore')->withTrashed();
    Route::delete('/{purchase}/force', [PurchaseOrderController::class, 'forceDestroy'])->middleware('permission:delete_purchase')->name('force')->withTrashed();
});
