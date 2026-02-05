<?php

use Illuminate\Support\Facades\Route;
use Modules\Finance\Http\Controllers\{
    AccountController,
    BudgetController,
    CategoryController,
    ExchangeRateController,
    FinanceDashboardController,
    FinanceReportController,
    FinancialAdvisorController,
    FinancialPlanController,
    RecurringTransactionController,
    SavingsGoalController,
    SmartInputController,
    SmartInputHistoryController,
    TransactionController
};

Route::middleware(['auth', 'verified'])
    ->prefix('dashboard/finance')
    ->name('dashboard.finance.')
    ->group(function () {
        Route::get('/', [FinanceDashboardController::class, 'index'])->name('index');
        Route::get('/reports', [FinanceReportController::class, 'index'])->name('reports');
        Route::get('/reports/category-trend', [FinanceReportController::class, 'categoryTrend'])->name('reports.category-trend');

        Route::resource('accounts', AccountController::class);

        Route::resource('categories', CategoryController::class)->except(['show']);

        Route::resource('transactions', TransactionController::class)->except(['show', 'edit']);
        Route::post('transactions/bulk-update', [TransactionController::class, 'bulkUpdate'])
            ->name('transactions.bulk-update');
        Route::post('transactions/bulk-destroy', [TransactionController::class, 'bulkDestroy'])
            ->name('transactions.bulk-destroy');
        Route::post('transactions/link-as-transfer', [TransactionController::class, 'linkAsTransfer'])
            ->name('transactions.link-as-transfer');
        Route::post('transactions/{transaction}/reconcile', [TransactionController::class, 'reconcile'])
            ->name('transactions.reconcile');
        Route::post('transactions/{transaction}/unreconcile', [TransactionController::class, 'unreconcile'])
            ->name('transactions.unreconcile');
        Route::post('transactions/conversion-preview', [TransactionController::class, 'conversionPreview'])
            ->name('transactions.conversion-preview');
        Route::get('transactions/export', [TransactionController::class, 'export'])
            ->name('transactions.export');
        Route::get('transactions/import', [TransactionController::class, 'import'])
            ->name('transactions.import');
        Route::post('transactions/import/preview', [TransactionController::class, 'importPreview'])
            ->name('transactions.import.preview');
        Route::post('transactions/import', [TransactionController::class, 'importStore'])
            ->name('transactions.import.store');

        Route::resource('budgets', BudgetController::class)->except(['show']);
        Route::post('budgets/{budget}/refresh', [BudgetController::class, 'refresh'])
            ->name('budgets.refresh');

        Route::resource('exchange-rates', ExchangeRateController::class)
            ->parameters(['exchange-rates' => 'exchangeRate'])
            ->except(['show']);

        Route::resource('recurring-transactions', RecurringTransactionController::class)
            ->parameters(['recurring-transactions' => 'recurringTransaction'])
            ->except(['show']);
        Route::post('recurring-transactions/{recurringTransaction}/toggle', [RecurringTransactionController::class, 'toggle'])
            ->name('recurring-transactions.toggle');
        Route::get('recurring-transactions/{recurringTransaction}/preview', [RecurringTransactionController::class, 'preview'])
            ->name('recurring-transactions.preview');
        Route::post('recurring-transactions/process', [RecurringTransactionController::class, 'process'])
            ->name('recurring-transactions.process');
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
            Route::post('transfer', [SavingsGoalController::class, 'transfer'])
                ->name('transfer');
            Route::post('link-transaction', [SavingsGoalController::class, 'linkTransaction'])
                ->name('link-transaction');
            Route::delete('contributions/{contribution}', [SavingsGoalController::class, 'unlinkContribution'])
                ->name('unlink-contribution');
            Route::post('pause', [SavingsGoalController::class, 'pause'])
                ->name('pause');
            Route::post('resume', [SavingsGoalController::class, 'resume'])
                ->name('resume');
        });

        Route::resource('plans', FinancialPlanController::class);
        Route::get('plans/{plan}/compare', [FinancialPlanController::class, 'compare'])
            ->name('plans.compare');

        // AI Advisor routes
        Route::get('advisor', [FinancialAdvisorController::class, 'index'])
            ->name('advisor');
        Route::post('advisor/send', [FinancialAdvisorController::class, 'sendMessage'])
            ->middleware('throttle:20,1')
            ->name('advisor.send');
        Route::delete('advisor/conversations/{conversationId}', [FinancialAdvisorController::class, 'destroyConversation'])
            ->name('advisor.conversations.destroy');

        // Smart Input routes
        Route::get('smart-input', [SmartInputController::class, 'index'])
            ->name('smart-input');
        Route::post('smart-input/parse-voice', [SmartInputController::class, 'parseVoice'])
            ->name('smart-input.parse-voice');
        Route::post('smart-input/parse-receipt', [SmartInputController::class, 'parseReceipt'])
            ->name('smart-input.parse-receipt');
        Route::post('smart-input/parse-text', [SmartInputController::class, 'parseText'])
            ->name('smart-input.parse-text');
        Route::post('smart-input/parse-text-image', [SmartInputController::class, 'parseTextWithImage'])
            ->name('smart-input.parse-text-image');
        Route::post('smart-input', [SmartInputController::class, 'store'])
            ->name('smart-input.store');

        // Smart Input History
        Route::get('smart-input-history', [SmartInputHistoryController::class, 'index'])
            ->name('smart-input-history.index');
        Route::delete('smart-input-history/{history}', [SmartInputHistoryController::class, 'destroy'])
            ->name('smart-input-history.destroy');
    });
