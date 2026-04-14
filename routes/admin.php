<?php

use App\Http\Controllers\RestockController;
use App\Http\Controllers\DistributionController;
use App\Http\Resources\StockMutationResource;
use App\Models\StockMutations;

use Illuminate\Support\Facades\Route;

Route::prefix('/warehouses')->group(function () {
    Route::get('restocks', [RestockController::class, 'index']);
    Route::post('restocks', [RestockController::class, 'store']);
    Route::get('restocks/{restock}', [RestockController::class, 'show']);
    Route::put('restocks/{restock}', [RestockController::class, 'update']);
    Route::delete('restocks/{restock}', [RestockController::class, 'destroy']);
    Route::post('restocks/{restock}/confirm', [RestockController::class, 'confirm']);
});

Route::name('distribution.')->group(function () {
    Route::get('/distributions', [DistributionController::class, 'index'])->name('index');
    Route::get('/distributions/{distribution}', [DistributionController::class, 'show'])->name('show');
    Route::post('/distributions', [DistributionController::class, 'store'])->name('store');
    Route::put('/distributions/{distribution}', [DistributionController::class, 'update'])->name('update');
    Route::delete('/distributions/{distribution}', [DistributionController::class, 'destroy'])->name('destroy');
});

Route::get('/mutations', function () {
    return StockMutationResource::collection(
        StockMutations::latest()->get()
    );
});