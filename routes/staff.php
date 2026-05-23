<?php

use App\Http\Controllers\Staff\DistributionStatusController;
use Illuminate\Support\Facades\Route;

Route::prefix('/distributions')->name('distribution.')->group(function () {
Route::patch('/{distribution}/status',[DistributionStatusController::class, 'updateStatus'])->name('status');
    });
