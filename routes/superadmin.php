<?php
use App\Http\Controllers\SuperAdmin\RestockStatusController;
use App\Http\Controllers\SuperAdmin\TransferStatusController;
use Illuminate\Support\Facades\Route;

Route::prefix('/superadmin')->name('superadmin.')->middleware('auth:sanctum')->group(function () {
    Route::patch('restocks/{restock}/status', [RestockStatusController::class, 'patch'])->middleware('permission:confirm_restock')->name('restock.status.patch');
    Route::get('restocks/{restock}/status/allowed', [RestockStatusController::class, 'allowedTransitions'])->middleware('permission:confirm_restock')->name('restock.status.allowed');

    Route::patch('transfers/{transfer}/status', [TransferStatusController::class, 'patch'])->name('transfer.status.patch');
    Route::get('transfers/{transfer}/status/allowed', [TransferStatusController::class, 'allowedTransitions'])->name('transfer.status.allowed');
});
