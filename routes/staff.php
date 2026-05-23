<?php

use App\Http\Controllers\Staff\DistributionStatusController;
use App\Http\Controllers\ReturnController;
use Illuminate\Support\Facades\Route;

Route::prefix('/staff')->middleware('auth:sanctum')->name('staff.')->group(function () {

    Route::patch('/distributions/{distribution}/confirm', [DistributionStatusController::class, 'confirm'])->name('distribution.confirm');
    Route::get('/distributions/{distribution}/status/allowed', [DistributionStatusController::class, 'allowedTransitions'])->name('distribution.status.allowed');

    Route::prefix('/returns')->name('return.')->group(function () {
        Route::get('/', [ReturnController::class, 'index'])->name('index');
        Route::post('/', [ReturnController::class, 'store'])->name('store');
        Route::get('/{stockReturn}', [ReturnController::class, 'show'])->name('show');
        Route::put('/{stockReturn}', [ReturnController::class, 'update'])->name('update');
        Route::delete('/{stockReturn}', [ReturnController::class, 'destroy'])->name('destroy');
    });

});