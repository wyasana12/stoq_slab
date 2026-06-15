<?php

use App\Http\Controllers\Admin\BatchController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PermissionController;
use App\Http\Controllers\Auth\RoleController;
use App\Http\Controllers\Auth\UserController;
use App\Http\Controllers\RegionController;
use App\Http\Controllers\SuperAdmin\MasterData\CategoryController;
use App\Http\Controllers\SuperAdmin\MasterData\ProductController;
use App\Http\Controllers\SuperAdmin\MasterData\WarehouseController;
use App\Http\Controllers\SuperAdmin\MasterData\SupplierController;
use App\Http\Controllers\SuperAdmin\MasterData\UnitController;
use App\Http\Controllers\SuperAdmin\ProductReceivingController;
use App\Http\Controllers\SuperAdmin\PurchaseOrderController;
use App\Http\Controllers\DssController;
use App\Http\Controllers\SuperAdmin\AlertConfigController;
use App\Http\Controllers\SuperAdmin\MasterData\StoreController;
use App\Http\Controllers\SuperAdmin\MasterData\RackController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [LoginController::class, 'me']);
    Route::put('/profile', [LoginController::class, 'update']);
});

Route::prefix('/stores')->middleware('auth:sanctum')->name('store.')->group(function (){
    Route::get('', [StoreController::class, 'index'])->middleware('permission:view_store')->name('index');
    Route::get('/dropdown', [StoreController::class, 'dropdown'])->name('dropdown');
    Route::post('/create', [StoreController::class, 'store'])->name('create');
    Route::get('/{store}', [StoreController::class, 'show'])->middleware('permission:view_store')->name('show');
    Route::put('/{store}', [StoreController::class, 'update'])->middleware('permission:edit_store')->name('update');
    Route::delete('/{store}', [StoreController::class, 'destroy'])->middleware('permission:delete_store')->name('delete');
});

Route::prefix('/configs')->middleware('auth:sanctum')->name('config.')->group(function ()
 {
    Route::get('/alerts', [AlertConfigController::class, 'index'])->name('alert.index');
    Route::post('/alerts/create', [AlertConfigController::class, 'store'])->name('alert.create');
    Route::put('/alerts/{alert}', [AlertConfigController::class, 'update'])->name('alert.update');
    Route::delete('/alerts/{alert}', [AlertConfigController::class, 'destroy'])->name('alert.delete');
});

Route::prefix('/permissions')->middleware(['auth:sanctum', 'role:super-admin'])->name('permission.')->group(function () {
    Route::get('', [PermissionController::class, 'index'])->middleware('permission:view_permission')->name('index');
    Route::delete('/{permission}', [PermissionController::class, 'destroy'])->middleware('permission:delete_permission')->name('delete');
});

Route::prefix('/roles')->middleware(['auth:sanctum', 'role:super-admin'])->name('role.')->group(function () {
    Route::get('', [RoleController::class, 'index'])->middleware('permission:view_role')->name('index');
    Route::post('/create', [RoleController::class, 'store'])->middleware('permission:create_role')->name('create');
    Route::get('/{role}', [RoleController::class, 'show'])->middleware('permission:view_role');
    Route::put('/{role}', [RoleController::class, 'update'])->middleware('permission:edit_role')->name('update');
    Route::delete('/{role}', [RoleController::class, 'destroy'])->middleware('permission:delete_role')->name('delete');
    Route::post('/{role}/permissions', [RoleController::class, 'assignPermissions'])->middleware('permission:assign_permissions')->name('assignPermissions');
});

Route::prefix('/users')->middleware(['auth:sanctum', 'role:super-admin'])->name('user.')->group(function () {
    Route::get('', [UserController::class, 'index'])->middleware('permission:view_user')->name('index');
    Route::post('/create', [UserController::class, 'store'])->middleware('permission:create_user')->name('create');
    Route::get('/{user}', [UserController::class, 'show'])->middleware('permission:view_user');
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
    Route::get('/dropdown', [WarehouseController::class, 'dropdown'])->name('dropdown');
    Route::post('/create', [WarehouseController::class, 'store'])->middleware('permission:create_warehouse')->name('create');
    Route::get('/{warehouse}', [WarehouseController::class, 'show'])->middleware('permission:view_warehouse')->name('show');
    Route::put('/{warehouse}', [WarehouseController::class, 'update'])->middleware('permission:edit_warehouse')->name('update');
    Route::delete('/{warehouse}', [WarehouseController::class, 'destroy'])->middleware('permission:delete_warehouse')->name('delete');
});

Route::prefix('/racks')->middleware('auth:sanctum')->name('rack.')->group(function () {
    Route::get('/statistics', [RackController::class, 'statistics'])->name('statistics');
    Route::get('', [RackController::class, 'index'])->name('index');
    Route::post('/create', [RackController::class, 'store'])->name('create');
    Route::get('/{rack}', [RackController::class, 'show'])->name('show');
    Route::put('/{rack}', [RackController::class, 'update'])->name('update');
    Route::delete('/{rack}', [RackController::class, 'destroy'])->name('delete');
});

Route::prefix('/suppliers')->middleware('auth:sanctum')->name('supplier.')->group(function () {
    Route::get('', [SupplierController::class, 'index'])->middleware('permission:view_supplier')->name('index');
    Route::get('/dropdown', [SupplierController::class, 'dropdown'])->name('dropdown');
    Route::post('/create', [SupplierController::class, 'store'])->middleware('permission:create_supplier')->name('create');
    Route::get('/{supplier}', [SupplierController::class, 'show'])->middleware('permission:view_supplier')->name('show');
    Route::put('/{supplier}', [SupplierController::class, 'update'])->middleware('permission:edit_supplier')->name('update');
    Route::delete('/{supplier}', [SupplierController::class, 'destroy'])->middleware('permission:delete_supplier')->name('delete');
    Route::get('/{supplier}/products', [ProductController::class, 'getProductBySupplier'])->name('product.supplier');
});

Route::prefix('/categories')->middleware('auth:sanctum')->name('category.')->group(function () {
    Route::get('/dropdown', [CategoryController::class, 'dropdown'])->name('dropdown');
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
    Route::get('/confirmation', [PurchaseOrderController::class, 'confirmation'])->middleware('permission:confirm_purchase')->name('confirmation');
    Route::get('/dropdown', [PurchaseOrderController::class, 'dropdown'])->name('dropdown');
    Route::get('/trash', [PurchaseOrderController::class, 'trashed'])->middleware('permission:restore_purchase')->name('trash');
    Route::post('/request', [PurchaseOrderController::class, 'request'])->middleware('permission:create_purchase')->name('create');
    Route::get('/{purchase}', [PurchaseOrderController::class, 'show'])->middleware('permission:view_purchase')->name('show');
    Route::get('/{purchase}/products', [ProductController::class, 'getProductbyPurchaseOrder'])->name('product.purchase');
    Route::put('/{purchase}/update', [PurchaseOrderController::class, 'update'])->middleware('permission:edit_purchase')->name('update');
    Route::patch('/{purchase}/status', [PurchaseOrderController::class, 'status'])->middleware('permission:confirm_purchase')->name('status');
    Route::delete('/{purchase}/soft', [PurchaseOrderController::class, 'destroy'])->middleware('permission:delete_purchase')->name('destroy');
    Route::patch('/{purchase}/restore', [PurchaseOrderController::class, 'restore'])->middleware('permission:restore_purchase')->name('restore')->withTrashed();
    Route::delete('/{purchase}/force', [PurchaseOrderController::class, 'forceDestroy'])->middleware('permission:delete_purchase')->name('force')->withTrashed();
});

Route::prefix('/receives')->middleware('auth:sanctum')->name('receive.')->group(function () {
    Route::get('', [ProductReceivingController::class, 'index'])->middleware('permission:view_receive')->name('index');
    Route::get('/trashed', [ProductReceivingController::class, 'trashed'])->middleware('permission:restore_receive')->name('trashed');
    Route::post('/create', [ProductReceivingController::class, 'store'])->middleware('permission:create_receive');
    Route::get('/{receive}', [ProductReceivingController::class, 'show'])->middleware('permission:view_receive')->name('show');
    Route::patch('/{receive}/update', [ProductReceivingController::class, 'updateItemsAndStatus'])->middleware('permission:edit_receive')->name('update');
    Route::delete('/{receive}/soft', [ProductReceivingController::class, 'destroy'])->middleware('permission:delete_receive')->name('destroy');
    Route::patch('/{receive}/restore', [ProductReceivingController::class, 'restore'])->name('restore')->middleware('permission:restore_receive')->withTrashed();
    Route::delete('/{receive}/force', [ProductReceivingController::class, 'forceDestroy'])->name('force')->middleware('permission:delete_receive')->withTrashed();
});

Route::prefix('/batches')->middleware('auth:sanctum')->name('batch.')->group(function () {
    Route::get('', [BatchController::class, 'index'])->middleware('permission:view_batch')->name('index');
    Route::post('/print-labels', [BatchController::class, 'printBySelected']);
    Route::get('/{batch}', [BatchController::class, 'show'])->middleware('permission:view_batch')->name('show');
    Route::post('/{batch}/generate', [BatchController::class, 'generate'])->middleware('permission:generate_barcode')->name('generate');
    Route::get('/{batch}/qr/preview', [BatchController::class, 'preview'])->name('qr.preview');
    Route::get('/{batch}/qr/download', [BatchController::class, 'download'])->name('qr.download');
    Route::patch('/{batch}/status', [BatchController::class, 'updateStatus'])->name('status.update');
});
Route::prefix('/dss')->middleware('auth:sanctum')->name('dss.')->group(function () {
    Route::get('/analysis', [DssController::class, 'analysis'])->name('analysis');
    Route::get('/recommendations', [DssController::class, 'recommendations'])->name('recommendations');
});