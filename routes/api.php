<?php

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

Route::prefix('/regions')->group(function () {
    Route::get('/provinces', [RegionController::class, 'provinces']);
    Route::get('/regencies/{provinceId}', [RegionController::class, 'regencies']);
    Route::get('/districts/{regencyId}', [RegionController::class, 'districts']);
    Route::get('/villages/{districtId}', [RegionController::class, 'villages']);
});

Route::prefix('/warehouses')->middleware('auth:sanctum')->name('warehouse.')->group(function () {
    Route::get('', [WarehouseController::class, 'index'])->name('index');
    Route::post('/create', [WarehouseController::class, 'store'])->name('create');
    Route::get('/{warehouse}', [WarehouseController::class, 'show'])->name('show');
    Route::put('/{warehouse}', [WarehouseController::class, 'update'])->name('update');
    Route::delete('/{warehouse}', [WarehouseController::class, 'destroy'])->name('delete');
});

Route::prefix('/suppliers')->middleware('auth:sanctum')->name('supplier.')->group(function () {
    Route::get('', [SupplierController::class, 'index'])->name('index');
    Route::post('/create', [SupplierController::class, 'store'])->name('create');
    Route::get('/{supplier}', [SupplierController::class, 'show'])->name('show');
    Route::put('/{supplier}', [SupplierController::class, 'update'])->name('update');
    Route::delete('/{supplier}', [SupplierController::class, 'destroy'])->name('delete');
    Route::get('/{supplier}/products', [SupplierController::class, 'getProductBySupplier'])->name('product.supplier');
});

Route::prefix('/categories')->middleware('auth:sanctum')->name('category.')->group(function () {
    Route::get('', [CategoryController::class, 'index'])->name('index');
    Route::post('/create', [CategoryController::class, 'store'])->name('create');
    Route::put('/{category}', [CategoryController::class, 'update'])->name('update');
    Route::delete('/{category}', [CategoryController::class, 'destroy'])->name('destroy');
});

Route::prefix('/units')->middleware('auth:sanctum')->name('unit.')->group(function () {
    Route::get('', [UnitController::class, 'index'])->name('index');
    Route::post('/create', [UnitController::class, 'store'])->name('create');
    Route::put('/{unit}', [UnitController::class, 'update'])->name('update');
    Route::delete('/{unit}', [UnitController::class, 'destroy'])->name('delete');
});

Route::prefix('/products')->middleware('auth:sanctum')->name('product.')->group(function () {
    Route::get('', [ProductController::class, 'index'])->name('index');
    Route::get('/trash', [ProductController::class, 'trashed'])->name('trash');
    Route::post('/create', [ProductController::class, 'store'])->name('create');
    Route::get('/{product}', [ProductController::class, 'show'])->name('show');
    Route::put('/{product}', [ProductController::class, 'update'])->name('update');
    Route::delete('/{product}/soft', [ProductController::class, 'destroy'])->name('delete');
    Route::patch('/{product}/restore', [ProductController::class, 'restore'])->name('restore')->withTrashed();
    Route::delete('/{product}/force', [ProductController::class, 'forceDestroy'])->name('force')->withTrashed();
});

Route::prefix('/purchases')->middleware('auth:sanctum')->name('purchase.')->group(function () {
    Route::get('', [PurchaseOrderController::class, 'index'])->name('index');
    Route::get('/trash', [PurchaseOrderController::class, 'trashed'])->name('trash');
    Route::post('/request', [PurchaseOrderController::class, 'request'])->name('create');
    Route::get('/{purchase}', [PurchaseOrderController::class, 'show'])->name('show');
    Route::put('/{purchase}/update', [PurchaseOrderController::class, 'update'])->name('update');
    Route::patch('/{purchase}/status', [PurchaseOrderController::class, 'status'])->name('status');
    Route::delete('/{purchase}/soft', [PurchaseOrderController::class, 'destroy'])->name('destroy');
    Route::patch('/{purchase}/restore', [PurchaseOrderController::class, 'restore'])->name('restore')->withTrashed();
    Route::delete('/{purchase}/force', [PurchaseOrderController::class, 'forceDestroy'])->name('force')->withTrashed();
});
