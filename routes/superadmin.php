<?php

use App\Http\Controllers\SuperAdmin\ReturnStatusController;
use App\Http\Controllers\SuperAdmin\DisposalStatusController;
use App\Http\Controllers\SuperAdmin\RestockStatusController;
use App\Http\Controllers\SuperAdmin\TransferStatusController;
use App\Http\Controllers\SuperAdmin\MonitoringController;
use App\Http\Controllers\SuperAdmin\ReportExportController;
use Illuminate\Support\Facades\Route;

Route::prefix('/superadmin')->name('superadmin.')->middleware('auth:sanctum')->group(function () {
    Route::patch('/returns/{stockReturn}/confirm', [ReturnStatusController::class, 'confirm'])->name('return.confirm');
    Route::get('/returns/{stockReturn}/status/allowed', [ReturnStatusController::class, 'allowedTransitions'])->name('return.status.allowed');

    Route::patch('/disposals/{stockDisposal}/confirm', [DisposalStatusController::class, 'confirm'])->name('disposal.confirm');

    Route::patch('restocks/{restock}/status', [RestockStatusController::class, 'patch'])->middleware('permission:confirm_restock')->name('restock.status.patch');
    Route::get('restocks/{restock}/status/allowed', [RestockStatusController::class, 'allowedTransitions'])->middleware('permission:confirm_restock')->name('restock.status.allowed');

    Route::patch('transfers/{transfer}/status', [TransferStatusController::class, 'patch'])->middleware('permission:confirm_transfer')->name('transfer.status.patch');
    Route::get('transfers/{transfer}/status/allowed', [TransferStatusController::class, 'allowedTransitions'])->middleware('permission:confirm_transfer')->name('transfer.status.allowed');

    Route::prefix('/monitoring')->name('monitoring.')->group(function () {
        Route::get('/dashboard-summary', [MonitoringController::class, 'summary'])->name('dashboard-summary');
        Route::get('/summary', [MonitoringController::class, 'summary'])->name('summary');
        Route::get('/batches', [MonitoringController::class, 'batches'])->name('batches');
        Route::get('/activities', [MonitoringController::class, 'activities'])->name('activities');
        Route::get('/dashboard', [MonitoringController::class, 'dashboard'])->name('dashboard');
        Route::get('/alerts', [MonitoringController::class, 'alerts'])->name('alerts');
        Route::get('/export/csv', [MonitoringController::class, 'exportCsv'])->name('export.csv');
        Route::get('/export/xlsx', [MonitoringController::class, 'exportXlsx'])->name('export.xlsx');
    });
    Route::prefix('/reports')->name('reports.')->group(function () {
        Route::get('/preview', [ReportExportController::class, 'preview'])->name('preview');
        Route::get('/export', [ReportExportController::class, 'export'])->name('export');
    });
});
