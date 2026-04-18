<?php

use App\Http\Controllers\Auth\UserController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SuperAdmin\PurchaseOrderController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\RestockController;
use App\Http\Controllers\SuperAdmin\ReturnConfirmController;
use Illuminate\Support\Facades\Route;

Route::prefix('/superadmin')->name('superadmin.')->middleware('auth:sanctum')->group(function () {
    Route::get('/users', [UserController::class, 'index'])->name('user.index');
    Route::post('/users/create', [UserController::class, 'store'])->name('user.create');
    Route::get('/users/{user}', [UserController::class, 'show'])->name('user.show');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('user.update');

    Route::get('/products', [ProductController::class, 'index'])->name('product.index');
    Route::post('/products/create', [ProductController::class, 'store'])->name('product.create');
    Route::get('/products/{product}', [ProductController::class, 'show'])->name('product.show');
    Route::put('/products/{product}', [ProductController::class, 'update'])->name('product.update');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('product.delete');

    Route::get('/categories', [CategoryController::class, 'index'])->name('category.index');
    Route::post('/categories/create', [CategoryController::class, 'store'])->name('category.create');
    Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('category.update');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('category.destroy');

    Route::get('/units', [UnitController::class, 'index'])->name('unit.index');
    Route::post('/units/create', [UnitController::class, 'store'])->name('unit.create');
    Route::put('/units/{unit}', [UnitController::class, 'update'])->name('unit.update');
    Route::delete('/units/{unit}', [UnitController::class, 'destroy'])->name('unit.delete');

    Route::get('/suppliers', [SupplierController::class, 'index'])->name('supplier.index');
    Route::post('/suppliers/create', [SupplierController::class, 'store'])->name('supplier.create');
    Route::get('/suppliers/{supplier}', [SupplierController::class, 'show'])->name('supplier.show');
    Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->name('supplier.update');
    Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('supplier.delete');

    Route::get('/warehouses', [WarehouseController::class, 'index'])->name('warehouse.index');
    Route::post('/warehouses/create', [WarehouseController::class, 'store'])->name('warehouse.create');
    Route::get('/warehouses/{warehouse}', [WarehouseController::class, 'show'])->name('warehouse.show');
    Route::put('/warehouses/{warehouse}', [WarehouseController::class, 'update'])->name('warehouse.update');
    Route::delete('/warehouses/{warehouse}', [WarehouseController::class, 'destroy'])->name('warehouse.delete');

    Route::post('restocks/{restock}/confirm', [RestockController::class, 'confirm'])->name('restock.confirm');

    Route::patch('/returns/{stockReturn}/confirm', [ReturnConfirmController::class, 'updateStatus'])->name('return.confirm');

    Route::prefix('/purchases')->name('purchaseorder.')->group(function () {
    Route::get('', [PurchaseOrderController::class, 'index'])->name('index');
    Route::get('/trashed', [PurchaseOrderController::class, 'trashed'])->name('trash');
    Route::post('/request', [PurchaseOrderController::class, 'request'])->name('create');
    Route::get('/{purchase}', [PurchaseOrderController::class, 'show'])->name('show');
    Route::put('/{purchase}/update', [PurchaseOrderController::class, 'update'])->name('update');
    Route::patch('/{purchase}/status', [PurchaseOrderController::class, 'status'])->name('status');
    Route::delete('/{purchase}/soft', [PurchaseOrderController::class, 'destroy'])->name('destroy');
    Route::patch('/{purchase}/restore', [PurchaseOrderController::class, 'restore'])->name('restore')->withTrashed();
    Route::delete('/{purchase}/force', [PurchaseOrderController::class, 'forceDestroy'])->name('force')->withTrashed();
    });
});

