<?php

use App\Http\Middleware\ETagMiddleware;
use Illuminate\Support\Facades\Route;
use Modules\Finance\Http\Controllers\{
    Api\AccountApiController,
    Api\AssistantApiController,
    Api\BorrowingApiController,
    Api\BudgetApiController,
    Api\CategoryApiController,
    Api\DashboardApiController,
    Api\ExchangeRateApiController,
    Api\FinancialPlanApiController,
    Api\LoanApiController,
    Api\RecurringTransactionApiController,
    Api\ReportApiController,
    Api\SavingsGoalApiController,
    Api\SmartInputApiController,
    Api\TransactionApiController,
    FinanceReportController
};
use Modules\Finance\Http\Middleware\EnsureAssistantEnabled;

Route::middleware(['auth:sanctum', 'throttle:api-general'])->prefix('v1/finance')->group(function () {
    // Dashboard API (for mobile app home screen)
    Route::get('dashboard', [DashboardApiController::class, 'index'])
        ->name('api.finance.dashboard');

    // Account API — ETag on list/show for conditional fetching
    Route::get('accounts', [AccountApiController::class, 'index'])->middleware(ETagMiddleware::class)->name('api.finance.accounts.index');
    Route::get('accounts/{account}', [AccountApiController::class, 'show'])->middleware(ETagMiddleware::class)->name('api.finance.accounts.show');
    Route::post('accounts', [AccountApiController::class, 'store'])->name('api.finance.accounts.store');
    Route::put('accounts/{account}', [AccountApiController::class, 'update'])->name('api.finance.accounts.update');
    Route::patch('accounts/{account}', [AccountApiController::class, 'update']);
    Route::delete('accounts/{account}', [AccountApiController::class, 'destroy'])->name('api.finance.accounts.destroy');
    Route::delete('accounts/{account}/icon', [AccountApiController::class, 'destroyIcon'])->name('api.finance.accounts.icon.destroy');
    Route::get('accounts-summary', [AccountApiController::class, 'summary'])
        ->name('api.finance.accounts.summary');

    // Bank icon library (curated SVGs shipped with the app)
    Route::get('account-icons', [AccountApiController::class, 'iconLibrary'])
        ->name('api.finance.account-icons.index');
    Route::get('account-icons/{file}', [AccountApiController::class, 'showIcon'])
        ->name('api.finance.account-icons.show');

    Route::get('loans-summary', [LoanApiController::class, 'summary'])->name('api.finance.loans.summary');
    Route::apiResource('loans', LoanApiController::class)->names('api.finance.loans');
    Route::post('loans/{loan}/repayments', [LoanApiController::class, 'storeRepayment'])->name('api.finance.loans.repayments.store');
    Route::delete('loans/{loan}/repayments/{repayment}', [LoanApiController::class, 'destroyRepayment'])->name('api.finance.loans.repayments.destroy');

    Route::get('borrowings-summary', [BorrowingApiController::class, 'summary'])->name('api.finance.borrowings.summary');
    Route::apiResource('borrowings', BorrowingApiController::class)->names('api.finance.borrowings');
    Route::post('borrowings/{borrowing}/repayments', [BorrowingApiController::class, 'storeRepayment'])->name('api.finance.borrowings.repayments.store');
    Route::delete('borrowings/{borrowing}/repayments/{repayment}', [BorrowingApiController::class, 'destroyRepayment'])->name('api.finance.borrowings.repayments.destroy');

    // Category API — ETag on list/show (categories rarely change)
    Route::get('categories', [CategoryApiController::class, 'index'])->middleware(ETagMiddleware::class)->name('api.finance.categories.index');
    Route::get('categories/{category}', [CategoryApiController::class, 'show'])->middleware(ETagMiddleware::class)->name('api.finance.categories.show');
    Route::post('categories', [CategoryApiController::class, 'store'])->name('api.finance.categories.store');
    Route::put('categories/{category}', [CategoryApiController::class, 'update'])->name('api.finance.categories.update');
    Route::patch('categories/{category}', [CategoryApiController::class, 'update']);
    Route::delete('categories/{category}', [CategoryApiController::class, 'destroy'])->name('api.finance.categories.destroy');

    // Transaction API — export must be registered before the {transaction} binding
    Route::get('transactions/export', [TransactionApiController::class, 'export'])
        ->name('api.finance.transactions.export');
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

    // Deprecated: replaced by the assistant API below; kept for older mobile app versions.
    // Tighter limit due to AI provider costs.
    Route::prefix('smart-input')->name('api.finance.smart-input.')->middleware('throttle:api-ai')->group(function () {
        Route::post('parse-voice', [SmartInputApiController::class, 'parseVoice'])
            ->name('parse-voice');
        Route::post('parse-receipt', [SmartInputApiController::class, 'parseReceipt'])
            ->name('parse-receipt');
        Route::post('parse-text', [SmartInputApiController::class, 'parseText'])
            ->name('parse-text');
        Route::post('parse-text-image', [SmartInputApiController::class, 'parseTextWithImage'])
            ->name('parse-text-image');
        Route::post('transactions', [SmartInputApiController::class, 'storeTransaction'])
            ->name('transactions.store');
        Route::get('history', [SmartInputApiController::class, 'history'])
            ->name('history.index');
        Route::delete('history/{history}', [SmartInputApiController::class, 'destroyHistory'])
            ->name('history.destroy');
        Route::post('sync-offline', [SmartInputApiController::class, 'syncOffline'])
            ->name('sync-offline');
        Route::post('quick-entry', [SmartInputApiController::class, 'quickEntry'])
            ->name('quick-entry');
    });

    // Unified AI assistant API (Smart Input + AI Advisor) — hidden when the agent is disabled; AI-cost throttle on send only
    Route::prefix('assistant')->name('api.finance.assistant.')->middleware(EnsureAssistantEnabled::class)->group(function () {
        Route::get('conversations', [AssistantApiController::class, 'conversations'])->name('conversations.index');
        Route::get('conversations/{conversation}/messages', [AssistantApiController::class, 'messages'])
            ->whereNumber('conversation')
            ->name('conversations.messages');
        Route::patch('conversations/{conversation}', [AssistantApiController::class, 'rename'])
            ->whereNumber('conversation')
            ->name('conversations.rename');
        Route::delete('conversations/{conversation}', [AssistantApiController::class, 'destroy'])
            ->whereNumber('conversation')
            ->name('conversations.destroy');
        Route::post('send', [AssistantApiController::class, 'send'])->middleware('throttle:api-ai')->name('send');
        Route::post('messages/{message}/drafts/{index}/save', [AssistantApiController::class, 'saveDraft'])
            ->whereNumber(['message', 'index'])
            ->name('drafts.save');
        Route::post('confirm', [AssistantApiController::class, 'confirm'])->name('confirm');
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
        Route::patch('{exchangeRate}', [ExchangeRateApiController::class, 'update'])->name('update');
        Route::delete('{exchangeRate}', [ExchangeRateApiController::class, 'destroy'])->name('destroy');
    });

    // Report API — lower limit as reports are heavier queries
    Route::prefix('reports')->name('api.finance.reports.')->middleware('throttle:api-reports')->group(function () {
        Route::get('overview', [ReportApiController::class, 'overview'])->name('overview');
        Route::get('income-expense-trend', [ReportApiController::class, 'incomeExpenseTrend'])->name('incomeExpenseTrend');
        Route::get('category-breakdown', [ReportApiController::class, 'categoryBreakdown'])->name('categoryBreakdown');
        Route::get('account-distribution', [ReportApiController::class, 'accountDistribution'])->name('accountDistribution');
        Route::get('cashflow-analysis', [ReportApiController::class, 'cashflowAnalysis'])->name('cashflowAnalysis');
        Route::get('net-worth', [ReportApiController::class, 'netWorth'])->name('netWorth');
        Route::get('category-trend', [FinanceReportController::class, 'categoryTrend'])->name('categoryTrend');
    });
});
