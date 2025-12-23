<?php

use Illuminate\Support\Facades\Route;
use Modules\Notification\Http\Controllers\NotificationController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('notifications', NotificationController::class)->names('notification');
});
