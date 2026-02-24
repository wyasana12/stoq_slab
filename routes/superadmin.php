<?php

use App\Http\Controllers\CategoryController;
use Illuminate\Support\Facades\Route;

Route::get('/categories', [CategoryController::class, 'index'])->name('category.index');
Route::post('/categories/create', [CategoryController::class, 'store'])->name('category.create');
Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('category.update');
Route::get('/categories/{category}', [CategoryController::class, 'detail'])->name('category.detail');