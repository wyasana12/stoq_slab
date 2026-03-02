<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::prefix('/superadmin')->name('superadmin.')->group(function () {
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
});