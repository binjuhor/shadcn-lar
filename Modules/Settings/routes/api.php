<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Settings\Http\Controllers\SettingsController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('settings', SettingsController::class)->names('settings');

    Route::patch('/language', function (Request $request) {
        $validated = $request->validate([
            'language' => ['required', 'string', 'in:en,vi'],
        ]);

        $request->user()->update(['language' => $validated['language']]);

        return response()->json(['success' => true]);
    })->middleware('throttle:10,1')->name('language.update');
});
