<?php

use Illuminate\Support\Facades\Route;
use Modules\Finance\Http\Controllers\{
    Api\AccountApiController,
    Api\BudgetApiController,
    Api\CategoryApiController,
    Api\DashboardApiController,
    Api\ExchangeRateApiController,
    Api\FinancialPlanApiController,
    Api\RecurringTransactionApiController,
    Api\ReportApiController,
    Api\SavingsGoalApiController,
    Api\SmartInputApiController,
    Api\TransactionApiController,
    FinanceReportController
};

Route::middleware(['auth:sanctum'])->prefix('v1/finance')->group(function () {
    // Dashboard API (for mobile app home screen)
    Route::get('dashboard', [DashboardApiController::class, 'index'])
        ->name('api.finance.dashboard');

    // Account API
    Route::apiResource('accounts', AccountApiController::class);
    Route::get('accounts-summary', [AccountApiController::class, 'summary'])
        ->name('api.finance.accounts.summary');

    // Category API
    Route::apiResource('categories', CategoryApiController::class);

    // Transaction API
    Route::apiResource('transactions', TransactionApiController::class);
    Route::post('transactions/{transaction}/reconcile', [TransactionApiController::class, 'reconcile'])
        ->name('api.finance.transactions.reconcile');
    Route::post('transactions/{transaction}/unreconcile', [TransactionApiController::class, 'unreconcile'])
        ->name('api.finance.transactions.unreconcile');
    Route::get('transactions-summary', [TransactionApiController::class, 'summary'])
        ->name('api.finance.transactions.summary');
    Route::post('transactions/bulk-update', [TransactionApiController::class, 'bulkUpdate'])
        ->name('api.finance.transactions.bulk-update');
    Route::post('transactions/bulk-destroy', [TransactionApiController::class, 'bulkDestroy'])
        ->name('api.finance.transactions.bulk-destroy');
    Route::post('transactions/conversion-preview', [TransactionApiController::class, 'conversionPreview'])
        ->name('api.finance.transactions.conversion-preview');

    // Budget API
    Route::apiResource('budgets', BudgetApiController::class);
    Route::post('budgets/{budget}/refresh', [BudgetApiController::class, 'refresh'])
        ->name('api.finance.budgets.refresh');
    Route::get('budgets-summary', [BudgetApiController::class, 'summary'])
        ->name('api.finance.budgets.summary');

    // Savings Goal API
    Route::apiResource('savings-goals', SavingsGoalApiController::class);
    Route::prefix('savings-goals/{savings_goal}')->name('api.finance.savings-goals.')->group(function () {
        Route::post('contribute', [SavingsGoalApiController::class, 'contribute'])->name('contribute');
        Route::post('withdraw', [SavingsGoalApiController::class, 'withdraw'])->name('withdraw');
        Route::post('transfer', [SavingsGoalApiController::class, 'transfer'])->name('transfer');
        Route::post('link-transaction', [SavingsGoalApiController::class, 'linkTransaction'])->name('link-transaction');
        Route::delete('contributions/{contribution}', [SavingsGoalApiController::class, 'unlinkContribution'])->name('unlink-contribution');
        Route::post('pause', [SavingsGoalApiController::class, 'pause'])->name('pause');
        Route::post('resume', [SavingsGoalApiController::class, 'resume'])->name('resume');
    });
    Route::get('savings-goals-summary', [SavingsGoalApiController::class, 'summary'])
        ->name('api.finance.savings-goals.summary');

    // Recurring Transaction API
    Route::apiResource('recurring-transactions', RecurringTransactionApiController::class);
    Route::post('recurring-transactions/{recurringTransaction}/toggle', [RecurringTransactionApiController::class, 'toggle'])
        ->name('api.finance.recurring-transactions.toggle');
    Route::get('recurring-transactions/{recurringTransaction}/preview', [RecurringTransactionApiController::class, 'preview'])
        ->name('api.finance.recurring-transactions.preview');
    Route::get('recurring-transactions-upcoming', [RecurringTransactionApiController::class, 'upcoming'])
        ->name('api.finance.recurring-transactions.upcoming');
    Route::get('recurring-transactions-projection', [RecurringTransactionApiController::class, 'projection'])
        ->name('api.finance.recurring-transactions.projection');
    Route::post('recurring-transactions/process', [RecurringTransactionApiController::class, 'process'])
        ->name('api.finance.recurring-transactions.process');

    // Financial Plan API
    Route::apiResource('plans', FinancialPlanApiController::class);
    Route::get('plans/{plan}/compare', [FinancialPlanApiController::class, 'compare'])
        ->name('api.finance.plans.compare');

    // Smart Input API endpoints
    Route::prefix('smart-input')->name('api.finance.smart-input.')->group(function () {
        Route::post('parse-voice', [SmartInputApiController::class, 'parseVoice'])
            ->name('parse-voice');
        Route::post('parse-receipt', [SmartInputApiController::class, 'parseReceipt'])
            ->name('parse-receipt');
        Route::post('parse-text', [SmartInputApiController::class, 'parseText'])
            ->name('parse-text');
        Route::post('sync-offline', [SmartInputApiController::class, 'syncOffline'])
            ->name('sync-offline');
        Route::post('quick-entry', [SmartInputApiController::class, 'quickEntry'])
            ->name('quick-entry');
    });

    // Exchange Rate API
    Route::prefix('exchange-rates')->name('api.finance.exchange-rates.')->group(function () {
        Route::get('/', [ExchangeRateApiController::class, 'index'])->name('index');
        Route::post('/', [ExchangeRateApiController::class, 'store'])->name('store');
        Route::get('latest', [ExchangeRateApiController::class, 'latest'])->name('latest');
        Route::post('convert', [ExchangeRateApiController::class, 'convert'])->name('convert');
        Route::post('fetch', [ExchangeRateApiController::class, 'fetchRates'])->name('fetch');
        Route::get('currencies', [ExchangeRateApiController::class, 'currencies'])->name('currencies');
        Route::get('providers', [ExchangeRateApiController::class, 'providers'])->name('providers');
        Route::get('{exchangeRate}', [ExchangeRateApiController::class, 'show'])->name('show');
        Route::delete('{exchangeRate}', [ExchangeRateApiController::class, 'destroy'])->name('destroy');
    });

    // Report API
    Route::prefix('reports')->name('api.finance.reports.')->group(function () {
        Route::get('overview', [ReportApiController::class, 'overview'])->name('overview');
        Route::get('income-expense-trend', [ReportApiController::class, 'incomeExpenseTrend'])->name('incomeExpenseTrend');
        Route::get('category-breakdown', [ReportApiController::class, 'categoryBreakdown'])->name('categoryBreakdown');
        Route::get('account-distribution', [ReportApiController::class, 'accountDistribution'])->name('accountDistribution');
        Route::get('cashflow-analysis', [ReportApiController::class, 'cashflowAnalysis'])->name('cashflowAnalysis');
        Route::get('net-worth', [ReportApiController::class, 'netWorth'])->name('netWorth');
        Route::get('category-trend', [FinanceReportController::class, 'categoryTrend'])->name('categoryTrend');
    });
});
