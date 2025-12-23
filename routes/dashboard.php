<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Modules\Notification\Http\Controllers\AdminNotificationController;
use Modules\Notification\Http\Controllers\NotificationController;
use Modules\Notification\Http\Controllers\NotificationTemplateController;

Route::get('/', fn () => Inertia::render('dashboard/index'))->name('dashboard');

Route::prefix('/settings')->name('dashboard.settings.')->group(function () {
    Route::get('/', [SettingsController::class, 'profile'])->name('profile');
    Route::patch('/profile', [SettingsController::class, 'updateProfile'])->name('profile.update');

    Route::get('/account', [SettingsController::class, 'account'])->name('account');
    Route::patch('/account', [SettingsController::class, 'updateAccount'])->name('account.update');

    Route::get('/appearance', [SettingsController::class, 'appearance'])->name('appearance');
    Route::patch('/appearance', [SettingsController::class, 'updateAppearance'])->name('appearance.update');

    Route::get('/notifications', [SettingsController::class, 'notifications'])->name('notifications');
    Route::patch('/notifications', [SettingsController::class, 'updateNotifications'])->name('notifications.update');

    Route::get('/display', [SettingsController::class, 'display'])->name('display');
    Route::patch('/display', [SettingsController::class, 'updateDisplay'])->name('display.update');
});

Route::get('/apps', fn () => Inertia::render('apps/index'))->name('dashboard.apps');
Route::get('/chats', fn () => Inertia::render('chats/index'))->name('dashboard.chats');
Route::get('/charts', fn () => Inertia::render('charts/index'))->name('dashboard.charts');
Route::get('/mail', fn () => Inertia::render('mail/index'))->name('dashboard.mail');
Route::get('/tasks', fn () => Inertia::render('tasks/index'))->name('dashboard.tasks');
Route::get('/users', fn () => Inertia::render('users/index'))->name('dashboard.users');
Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
Route::get('/help-center', fn () => Inertia::render('coming-soon/index'))->name('dashboard.coming-soon');
Route::get('/chat-ai', fn () => Inertia::render('playground/dashboard-03'))->name('dashboard.03');

// Notifications
Route::prefix('/notifications')->name('dashboard.notifications.')->group(function () {
    Route::get('/', [NotificationController::class, 'index'])->name('index');
    Route::get('/list', [NotificationController::class, 'index'])->name('list');
    Route::get('/unread-count', [NotificationController::class, 'unreadCount'])->name('unread-count');
    Route::post('/{id}/read', [NotificationController::class, 'markAsRead'])->name('read');
    Route::post('/read-all', [NotificationController::class, 'markAllAsRead'])->name('read-all');
    Route::delete('/{id}', [NotificationController::class, 'destroy'])->name('destroy');

    // Templates (Admin)
    Route::resource('templates', NotificationTemplateController::class);
    Route::post('/templates/{template}/toggle-status', [NotificationTemplateController::class, 'toggleStatus'])->name('templates.toggle-status');

    // Send Notifications (Admin)
    Route::get('/send', [AdminNotificationController::class, 'send'])->name('send');
    Route::post('/send', [AdminNotificationController::class, 'sendNotification'])->name('send.store');
    Route::get('/search-users', [AdminNotificationController::class, 'searchUsers'])->name('search-users');
});
