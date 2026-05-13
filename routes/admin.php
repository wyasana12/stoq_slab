<?php

use App\Http\Controllers\RestockController;
use App\Http\Controllers\DistributionController;
use App\Http\Resources\StockMutationResource;
use App\Models\StockMutations;
use Illuminate\Support\Facades\Route;

Route::prefix('/warehouses')->group(function () {
    Route::get('restocks', [RestockController::class, 'index'])->middleware('permission:view_restock');
    Route::post('restocks', [RestockController::class, 'store'])->middleware('permission:create_restock');
    Route::get('restocks/{restock}', [RestockController::class, 'show'])->middleware('permission:view_restock');
    Route::put('restocks/{restock}', [RestockController::class, 'update'])->middleware('permission:edit_restock');
    Route::delete('restocks/{restock}', [RestockController::class, 'destroy'])->middleware('permission:delete_restock');
    //Route::post('restocks/{restock}/confirm', [RestockController::class, 'confirm'])->middleware('permission:view_restock');
});

    Route::get('/distributions', [DistributionController::class, 'index'])->middleware('permission:view_distribution');
    Route::get('/distributions/{distribution}', [DistributionController::class, 'show'])->middleware('permission:view_distribution');
    Route::post('/distributions', [DistributionController::class, 'store'])->middleware('permission:create_distribution');
    Route::put('/distributions/{distribution}', [DistributionController::class, 'update'])->middleware('permission:edit_distribution');
    Route::delete('/distributions/{distribution}', [DistributionController::class, 'destroy'])->middleware('permission:delete_distribution');
    Route::patch('/distributions/{distribution}/status', [DistributionController::class, 'updateStatus'])->middleware('permission:confirm_distribution');

Route::get('/mutations', function () {
    return StockMutationResource::collection(
        StockMutations::latest()->get()
    );
});