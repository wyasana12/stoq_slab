<?php

use App\Http\Controllers\RestockController;
use App\Http\Controllers\DistributionController;
use Illuminate\Support\Facades\Route;

Route::prefix('/warehouses')->group(function () {
    Route::get('restocks', [RestockController::class, 'index']);
    Route::post('restocks', [RestockController::class, 'store']);
    Route::get('restocks/{restock}', [RestockController::class, 'show']);
    Route::put('restocks/{restock}', [RestockController::class, 'update']);
    Route::delete('restocks/{restock}', [RestockController::class, 'destroy']);
    Route::post('restocks/{restock}/confirm', [RestockController::class, 'confirm']);
});

Route::get('/distributions', [DistributionController::class, 'index']);
Route::get('/distributions/{distribution}', [DistributionController::class, 'show']);
Route::post('/distributions', [DistributionController::class, 'store']);
Route::put('/distributions/{distribution}', [DistributionController::class, 'update']);
Route::delete('/distributions/{distribution}', [DistributionController::class, 'destroy']);