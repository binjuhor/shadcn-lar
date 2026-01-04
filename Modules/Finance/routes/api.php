<?php

use Illuminate\Support\Facades\Route;
use Modules\Finance\Http\Controllers\Api\SmartInputApiController;
use Modules\Finance\Http\Controllers\FinanceController;

Route::middleware(['auth:sanctum'])->prefix('v1/finance')->group(function () {
    Route::apiResource('finances', FinanceController::class)->names('finance');

    // Smart Input API endpoints
    Route::post('parse-voice', [SmartInputApiController::class, 'parseVoice'])
        ->name('api.finance.parse-voice');
    Route::post('parse-receipt', [SmartInputApiController::class, 'parseReceipt'])
        ->name('api.finance.parse-receipt');
    Route::post('parse-text', [SmartInputApiController::class, 'parseText'])
        ->name('api.finance.parse-text');
    Route::post('transactions', [SmartInputApiController::class, 'storeTransaction'])
        ->name('api.finance.transactions.store');
    Route::get('accounts', [SmartInputApiController::class, 'accounts'])
        ->name('api.finance.accounts');
    Route::get('categories', [SmartInputApiController::class, 'categories'])
        ->name('api.finance.categories');
});
