<?php

use App\Http\Controllers\SuperAdmin\RestockStatusController;
use Illuminate\Support\Facades\Route;

Route::prefix('/superadmin')->name('superadmin.')->middleware('auth:sanctum')->group(function () {
    Route::patch('restocks/{restock}/status', [RestockStatusController::class, 'patch'])->name('restock.status.patch');
    Route::get('restocks/{restock}/status/allowed', [RestockStatusController::class, 'allowedTransitions'])->name('restock.status.allowed');
});

