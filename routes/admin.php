<?php

use App\Http\Controllers\Admin\BatchController;
use App\Http\Controllers\Admin\RestockController;
use App\Http\Controllers\Admin\TransferController;
use App\Http\Controllers\SuperAdmin\ProductReceivingController;
use App\Http\Controllers\DistributionController;
use App\Http\Resources\StockMutationResource;
use App\Models\StockMutations;
use Illuminate\Support\Facades\Route;

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
        Route::post('/{batch}/generate', [BatchController::class, 'generate'])->name('generate');
    });

    Route::prefix('/restocks')->group(function () {
    Route::get('', [RestockController::class, 'index'])->middleware('permission:view_restock');
    Route::post('', [RestockController::class, 'store'])->middleware('permission:create_restock');
    Route::get('/{restock}', [RestockController::class, 'show'])->middleware('permission:view_restock');
    Route::put('/{restock}', [RestockController::class, 'update'])->middleware('permission:edit_restock');
    Route::delete('/{restock}', [RestockController::class, 'destroy'])->middleware('permission:delete_restock');
    });

    Route::get('/distributions', [DistributionController::class, 'index'])->middleware('permission:view_distribution');
    Route::get('/distributions/{distribution}', [DistributionController::class, 'show'])->middleware('permission:view_distribution');
    Route::post('/distributions', [DistributionController::class, 'store'])->middleware('permission:create_distribution');
    Route::put('/distributions/{distribution}', [DistributionController::class, 'update'])->middleware('permission:edit_distribution');
    Route::delete('/distributions/{distribution}', [DistributionController::class, 'destroy'])->middleware('permission:delete_distribution');
    Route::patch('/distributions/{distribution}/status', [DistributionController::class, 'updateStatus'])->middleware('permission:confirm_distribution');

    Route::prefix('/transfers')->group(function () {
        Route::get('', [TransferController::class, 'index']);
        Route::post('', [TransferController::class, 'store']);
        Route::get('/{transfer}', [TransferController::class, 'show']);
        Route::put('/{transfer}', [TransferController::class, 'update']);
        Route::delete('/{transfer}', [TransferController::class, 'destroy']);
    });

    Route::get('/mutations', function () {
        return StockMutationResource::collection(
            StockMutations::latest()->get()
        );
    });
});
