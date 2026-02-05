<?php

use Illuminate\Support\Facades\Route;
use Modules\Settings\Http\Controllers\{
    ModulesController,
    ProfileController,
    SettingsController
};

Route::middleware(['auth', 'verified'])->group(function () {
    // Language switch route
    Route::patch('/language', function (\Illuminate\Http\Request $request) {
        $validated = $request->validate([
            'language' => ['required', 'string', 'in:en,vi'],
        ]);

        $request->user()->update(['language' => $validated['language']]);

        return back();
    })->middleware('throttle:10,1')->name('language.update');

    // Settings routes
    Route::prefix('dashboard/settings')->name('dashboard.settings.')->group(function () {
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

        Route::get('/finance', [SettingsController::class, 'finance'])->name('finance');
        Route::patch('/finance', [SettingsController::class, 'updateFinance'])->name('finance.update');

        Route::get('/invoice', [SettingsController::class, 'invoice'])->name('invoice');
        Route::patch('/invoice', [SettingsController::class, 'updateInvoice'])->name('invoice.update');

        // Modules (Super Admin only)
        Route::get('/modules', [ModulesController::class, 'index'])->name('modules');
        Route::patch('/modules/toggle', [ModulesController::class, 'toggle'])->name('modules.toggle');
        Route::patch('/modules/reorder', [ModulesController::class, 'reorder'])->name('modules.reorder');
    });

    // Profile routes (user account management)
    Route::prefix('dashboard')->group(function () {
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    });
});
