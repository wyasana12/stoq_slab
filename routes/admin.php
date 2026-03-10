<?php

use App\Http\Controllers\RestockController;
use Illuminate\Support\Facades\Route;

Route::prefix('/warehouses')->group(function () {
    Route::get('restocks', [RestockController::class, 'index']);
    Route::post('restocks', [RestockController::class, 'store']);
    Route::get('restocks/{restock}', [RestockController::class, 'show']);
    Route::put('restocks/{restock}', [RestockController::class, 'update']);
    Route::delete('restocks/{restock}', [RestockController::class, 'destroy']);
    Route::post('restocks/{restock}/confirm', [RestockController::class, 'confirm']);
});