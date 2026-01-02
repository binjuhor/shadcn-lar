<?php

use Illuminate\Support\Facades\Route;
use Modules\Finance\Http\Controllers\AccountController;
use Modules\Finance\Http\Controllers\BudgetController;
use Modules\Finance\Http\Controllers\CategoryController;
use Modules\Finance\Http\Controllers\ExchangeRateController;
use Modules\Finance\Http\Controllers\FinanceDashboardController;
use Modules\Finance\Http\Controllers\FinanceReportController;
use Modules\Finance\Http\Controllers\SavingsGoalController;
use Modules\Finance\Http\Controllers\TransactionController;

Route::middleware(['auth', 'verified'])
    ->prefix('dashboard/finance')
    ->name('dashboard.finance.')
    ->group(function () {
        Route::get('/', [FinanceDashboardController::class, 'index'])->name('index');
        Route::get('/reports', [FinanceReportController::class, 'index'])->name('reports');

        Route::resource('accounts', AccountController::class);

        Route::resource('categories', CategoryController::class)->except(['show']);

        Route::resource('transactions', TransactionController::class)->except(['show', 'edit', 'update']);
        Route::post('transactions/{transaction}/reconcile', [TransactionController::class, 'reconcile'])
            ->name('transactions.reconcile');

        Route::resource('budgets', BudgetController::class)->except(['show']);
        Route::post('budgets/{budget}/refresh', [BudgetController::class, 'refresh'])
            ->name('budgets.refresh');

        Route::resource('exchange-rates', ExchangeRateController::class)
            ->parameters(['exchange-rates' => 'exchangeRate'])
            ->except(['show']);
        Route::post('exchange-rates/fetch', [ExchangeRateController::class, 'fetchRates'])
            ->name('exchange-rates.fetch');
        Route::post('exchange-rates/convert', [ExchangeRateController::class, 'convert'])
            ->name('exchange-rates.convert');

        Route::resource('savings-goals', SavingsGoalController::class)
            ->parameters(['savings-goals' => 'savingsGoal']);

        Route::prefix('savings-goals/{savingsGoal}')->name('savings-goals.')->group(function () {
            Route::post('contribute', [SavingsGoalController::class, 'contribute'])
                ->name('contribute');
            Route::post('withdraw', [SavingsGoalController::class, 'withdraw'])
                ->name('withdraw');
            Route::post('link-transaction', [SavingsGoalController::class, 'linkTransaction'])
                ->name('link-transaction');
            Route::delete('contributions/{contribution}', [SavingsGoalController::class, 'unlinkContribution'])
                ->name('unlink-contribution');
            Route::post('pause', [SavingsGoalController::class, 'pause'])
                ->name('pause');
            Route::post('resume', [SavingsGoalController::class, 'resume'])
                ->name('resume');
        });
    });
