<?php

use Illuminate\Support\Facades\Route;
use Modules\Permission\Http\Controllers\PermissionController;
use Modules\Permission\Http\Controllers\RoleController;
use Modules\Permission\Http\Controllers\UserController;

Route::middleware(['auth', 'verified'])->prefix('dashboard')->name('dashboard.')->group(function () {
    // Roles Management
    Route::resource('roles', RoleController::class);

    // Permissions Management
    Route::resource('permissions', PermissionController::class);

    // Users Management
    Route::resource('users', UserController::class);

    // Bulk role assignment
    Route::post('users/bulk-assign-roles', [UserController::class, 'bulkAssignRoles'])->name('users.bulk-assign-roles');
});
