<?php

use Illuminate\Support\Facades\Route;
use Modules\Notification\Http\Controllers\AdminNotificationController;
use Modules\Notification\Http\Controllers\NotificationController;
use Modules\Notification\Http\Controllers\NotificationTemplateController;

Route::middleware(['auth:sanctum'])->prefix('v1/notification')->group(function () {
    // User notifications
    Route::get('/', [NotificationController::class, 'index'])->name('api.notifications.index');
    Route::get('/recent', [NotificationController::class, 'recent'])->name('api.notifications.recent');
    Route::get('/unread-count', [NotificationController::class, 'unreadCount'])->name('api.notifications.unread-count');
    Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('api.notifications.mark-all-read');
    Route::delete('/bulk', [NotificationController::class, 'destroyMultiple'])->name('api.notifications.destroy-multiple');

    Route::prefix('{notification}')->group(function () {
        Route::get('/', [NotificationController::class, 'show'])->name('api.notifications.show');
        Route::post('/mark-read', [NotificationController::class, 'markAsRead'])->name('api.notifications.mark-read');
        Route::post('/mark-unread', [NotificationController::class, 'markAsUnread'])->name('api.notifications.mark-unread');
        Route::delete('/', [NotificationController::class, 'destroy'])->name('api.notifications.destroy');
    });

    // Admin: Templates
    Route::prefix('templates')->group(function () {
        Route::post('/{template}/toggle-status', [NotificationTemplateController::class, 'toggleStatus'])
            ->name('api.notifications.templates.toggle-status');
    });

    // Admin: Send notifications
    Route::prefix('admin')->group(function () {
        Route::get('/users/search', [AdminNotificationController::class, 'searchUsers'])
            ->name('api.notifications.admin.search-users');
        Route::post('/send', [AdminNotificationController::class, 'sendNotification'])
            ->name('api.notifications.admin.send');
    });
});
