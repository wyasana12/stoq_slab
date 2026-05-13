<?php

use App\Http\Controllers\SuperAdmin\RestockStatusController;
use Illuminate\Support\Facades\Route;

Route::prefix('/superadmin')->name('superadmin.')->middleware('auth:sanctum')->group(function () {
    Route::patch('restocks/{restock}/status', [RestockStatusController::class, 'patch'])->middleware('permission:confirm_restock')->name('restock.status.patch');
    Route::get('restocks/{restock}/status/allowed', [RestockStatusController::class, 'allowedTransitions'])->middleware('permission:confirm_restock')->name('restock.status.allowed');
});

