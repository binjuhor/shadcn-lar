<?php

use Illuminate\Support\Facades\Route;
use Modules\Finance\Http\Controllers\AccountController;
use Modules\Finance\Http\Controllers\BudgetController;
use Modules\Finance\Http\Controllers\CategoryController;
use Modules\Finance\Http\Controllers\FinanceDashboardController;
use Modules\Finance\Http\Controllers\TransactionController;

Route::middleware(['auth', 'verified'])
    ->prefix('dashboard/finance')
    ->name('dashboard.finance.')
    ->group(function () {
        Route::get('/', [FinanceDashboardController::class, 'index'])->name('index');

        Route::resource('accounts', AccountController::class);

        Route::resource('categories', CategoryController::class)->except(['show']);

        Route::resource('transactions', TransactionController::class)->except(['show', 'edit', 'update']);
        Route::post('transactions/{transaction}/reconcile', [TransactionController::class, 'reconcile'])
            ->name('transactions.reconcile');

        Route::resource('budgets', BudgetController::class)->except(['show']);
        Route::post('budgets/{budget}/refresh', [BudgetController::class, 'refresh'])
            ->name('budgets.refresh');
    });
