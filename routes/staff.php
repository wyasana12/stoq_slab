<?php

use App\Http\Controllers\Staff\DashboardController;
use App\Http\Controllers\Staff\DistributionStatusController;
use App\Http\Controllers\Staff\ExpiredConditionController;
use App\Http\Controllers\Staff\DisposalController;
use App\Http\Controllers\ReturnController;
use Illuminate\Support\Facades\Route;

Route::prefix('/staff')->middleware('auth:sanctum')->name('staff.')->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');

    Route::patch('/distributions/{distribution}/confirm', [DistributionStatusController::class, 'confirm'])
        ->name('distribution.confirm');

    Route::get('/distributions/{distribution}/status/allowed', [DistributionStatusController::class, 'allowedTransitions'])
        ->name('distribution.status.allowed');

    Route::get('/distributions/{distribution}/surat-jalan', [DistributionStatusController::class, 'downloadSuratJalan'])
        ->name('distribution.download.surat-jalan');

    Route::get('/distributions/{distribution}/shipped-proof', [DistributionStatusController::class, 'downloadShippedProof'])
        ->name('distribution.download.shipped-proof');

    Route::get('/distributions/{distribution}/delivered-proof', [DistributionStatusController::class, 'downloadDeliveredProof'])
        ->name('distribution.download.delivered-proof');

    Route::post('/expired/{batch}/action', [ExpiredConditionController::class, 'process'])
        ->name('expired.action');

    Route::prefix('/returns')->name('return.')->group(function () {
        Route::get('/scan-barcode', [ReturnController::class, 'scanBarcode'])->name('scan-barcode');
        Route::get('/', [ReturnController::class, 'index'])->name('index');
        Route::post('/', [ReturnController::class, 'store'])->name('store');
        Route::get('/{stockReturn}', [ReturnController::class, 'show'])->name('show');
        Route::put('/{stockReturn}', [ReturnController::class, 'update'])->name('update');
        Route::delete('/{stockReturn}', [ReturnController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('/disposals')->name('disposal.')->group(function () {
        Route::get('/', [DisposalController::class, 'index'])->name('index');
        Route::post('/', [DisposalController::class, 'store'])->name('store');
        Route::get('/{stockDisposal}', [DisposalController::class, 'show'])->name('show');
    });
});
