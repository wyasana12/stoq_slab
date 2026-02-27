<?php

use App\Http\Resources\StockMutationResource;
use Illuminate\Support\Facades\Route;
use App\Models\StockMutations;

Route::get('/mutations', function () {
    return StockMutationResource::collection(
        StockMutations::latest()->get()
    );
});
