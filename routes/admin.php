<?php

use App\Http\Controllers\RestockController;
use App\Http\Controllers\DistributionController;
use App\Http\Resources\StockMutationResource;
use App\Models\StockMutations;

use Illuminate\Support\Facades\Route;

Route::prefix('/admin')->group(function () {
    Route::get('restocks', [RestockController::class, 'index'])->name('restocks.index');
    Route::post('restocks', [RestockController::class, 'store'])->name('restocks.store');
    Route::get('restocks/{restock}', [RestockController::class, 'show'])->name('restocks.show');
    Route::put('restocks/{restock}', [RestockController::class, 'update'])->name('restocks.update');
    Route::delete('restocks/{restock}', [RestockController::class, 'destroy'])->name('restocks.delete');

    Route::get('/distributions', [DistributionController::class, 'index']);
    Route::get('/distributions/{distribution}', [DistributionController::class, 'show']);
    Route::post('/distributions', [DistributionController::class, 'store']);
    Route::put('/distributions/{distribution}', [DistributionController::class, 'update']);
    Route::delete('/distributions/{distribution}', [DistributionController::class, 'destroy']);
});

Route::get('/mutations', function () {
    return StockMutationResource::collection(
        StockMutations::latest()->get()
    );
});