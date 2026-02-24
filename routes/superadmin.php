<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\UnitController;
use Illuminate\Support\Facades\Route;

Route::get('/categories', [CategoryController::class, 'index'])->name('category.index');
Route::post('/categories/create', [CategoryController::class, 'store'])->name('category.create');
Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('category.update');
Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('category.destroy');

Route::get('/units', [UnitController::class, 'index'])->name('unit.index');
Route::post('/units/create', [UnitController::class, 'store'])->name('unit.create');
Route::put('/units/{unit}', [UnitController::class, 'update'])->name('unit.update');
Route::delete('/units/{unit}', [UnitController::class, 'destroy'])->name('unit.delete');