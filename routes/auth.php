<?php

use App\Http\Controllers\Auth\RegistedAndLoginController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [RegistedAndLoginController::class, 'login'])->name('login');
