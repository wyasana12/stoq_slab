<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PermissionController;
use App\Http\Controllers\Auth\RoleController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [LoginController::class, 'login'])->name('login');

Route::prefix('/superadmin/auth')->name('superadmin.auth.')->group(function () {
    Route::get('/roles', [RoleController::class, 'index'])->name('role.index');
    Route::post('/roles/create', [RoleController::class, 'store'])->name('role.create');
    Route::get('/roles/{role}', [RoleController::class, 'show'])->name('role.show');
    Route::put('/roles/{role}', [RoleController::class, 'update'])->name('role.update');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('role.delete');

    Route::get('/permissions', [PermissionController::class, 'index'])->name('permission.index');
    Route::post('/permissions/create', [PermissionController::class, 'store'])->name('permission.create');
    Route::put('/permissions/{permission}', [PermissionController::class, 'update'])->name('permission.update');
    Route::delete('/permissions/{permission}', [PermissionController::class, 'destroy'])->name('permission.delete');

    Route::post('/roles/{role}/permissions', [RoleController::class, 'assignPermissions'])->name('role.assignPermissions');
});
