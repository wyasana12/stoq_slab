<?php
    use App\Http\Controllers\DistributionController;
    use Illuminate\Support\Facades\Route;

    Route::get('/distributions', [DistributionController::class, 'index']);
    Route::get('/distributions/{distribution}', [DistributionController::class, 'show']);
    Route::post('/distributions', [DistributionController::class, 'store']);
    Route::put('/distributions/{distribution}', [DistributionController::class, 'update']);
    Route::delete('/distributions/{distribution}', [DistributionController::class, 'destroy']);
