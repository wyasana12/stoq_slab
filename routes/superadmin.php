<?php

use App\Http\Controllers\Auth\UserController;
use App\Http\Controllers\SuperAdmin\RestockStatusController;
use Illuminate\Support\Facades\Route;

Route::prefix('/superadmin')->name('superadmin.')->middleware('auth:sanctum')->group(function () {
    Route::get('/users', [UserController::class, 'index'])->name('user.index');
    Route::post('/users/create', [UserController::class, 'store'])->name('user.create');
    Route::get('/users/{user}', [UserController::class, 'show'])->name('user.show');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('user.update');

    Route::patch('restocks/{restock}/status', [RestockStatusController::class, 'patch'])->name('restock.status.patch');
    Route::get('restocks/{restock}/status/allowed', [RestockStatusController::class, 'allowedTransitions'])->name('restock.status.allowed');
});

