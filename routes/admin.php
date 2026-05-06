<?php

use App\Http\Controllers\Admin\BatchController;
use App\Http\Controllers\RestockController;
use App\Http\Controllers\DistributionController;
use App\Http\Resources\StockMutationResource;
use App\Models\StockMutations;
use App\Http\Controllers\Admin\ProductReceivingController;
use Illuminate\Support\Facades\Route;



Route::get('/mutations', function () {
    return StockMutationResource::collection(
        StockMutations::latest()->get()
    );
});

Route::prefix('/admin')->middleware('auth:sanctum')->name('admin.')->group(function () {
    Route::prefix('/receives')->name('receive.')->group(function () {
        Route::get('', [ProductReceivingController::class, 'index'])->name('index');
        Route::get('/trashed', [ProductReceivingController::class, 'trashed'])->name('trashed');
        Route::get('/{receive}', [ProductReceivingController::class, 'show'])->name('show');
        Route::patch('/{receive}/update', [ProductReceivingController::class, 'updateItemsAndStatus'])->name('update');
        Route::delete('/{receive}/soft', [ProductReceivingController::class, 'destroy'])->name('destroy');
        Route::patch('/{receive}/restore', [ProductReceivingController::class, 'restore'])->name('restore')->withTrashed();
        Route::delete('/{receive}/force', [ProductReceivingController::class, 'forceDestroy'])->name('force')->withTrashed();
    });

    Route::prefix('/batches')->name('batch.')->group(function () {
        Route::get('', [BatchController::class, 'index'])->name('index');
        Route::get('/{batch}', [BatchController::class, 'show'])->name('show');
    });
    Route::prefix('/restocks')->group(function () {
        Route::get('', [RestockController::class, 'index']);
        Route::post('', [RestockController::class, 'store']);
        Route::get('/{restock}', [RestockController::class, 'show']);
        Route::put('/{restock}', [RestockController::class, 'update']);
        Route::delete('/{restock}', [RestockController::class, 'destroy']);
        Route::post('/{restock}/confirm', [RestockController::class, 'confirm']);
    });

    Route::get('/distributions', [DistributionController::class, 'index']);
    Route::get('/distributions/{distribution}', [DistributionController::class, 'show']);
    Route::post('/distributions', [DistributionController::class, 'store']);
    Route::put('/distributions/{distribution}', [DistributionController::class, 'update']);
    Route::delete('/distributions/{distribution}', [DistributionController::class, 'destroy']);
});