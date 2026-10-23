<?php

use Illuminate\Support\Facades\Route;
use Modules\Finance\Http\Controllers\{
    AccountController,
    AssistantController,
    BorrowingController,
    BudgetController,
    CategoryController,
    ExchangeRateController,
    FinanceDashboardController,
    FinanceReportController,
    FinancialPlanController,
    LoanController,
    RecurringTransactionController,
    SavingsGoalController,
    SmartInputHistoryController,
    TransactionController
};
use Modules\Finance\Http\Middleware\EnsureAssistantEnabled;

Route::middleware(['auth', 'verified'])
    ->prefix('dashboard/finance')
    ->name('dashboard.finance.')
    ->group(function () {
        Route::get('/', [FinanceDashboardController::class, 'index'])->name('index');
        Route::get('/reports', [FinanceReportController::class, 'index'])->name('reports');
        Route::get('/reports/category-trend', [FinanceReportController::class, 'categoryTrend'])->name('reports.category-trend');

        Route::resource('accounts', AccountController::class);
        Route::delete('accounts/{account}/icon', [AccountController::class, 'destroyIcon'])
            ->name('accounts.icon.destroy');
        Route::get('account-icons/{file}', [AccountController::class, 'showIcon'])
            ->name('account-icons.show');

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
            Route::post('sync', [SavingsGoalController::class, 'sync'])
                ->name('sync');
        });

        Route::resource('plans', FinancialPlanController::class);
        Route::get('plans/{plan}/compare', [FinancialPlanController::class, 'compare'])
            ->name('plans.compare');

        Route::get('loans/search-expense-transactions', [LoanController::class, 'searchExpenseTransactions'])
            ->name('loans.search-expense-transactions');
        Route::resource('loans', LoanController::class)
            ->only(['index', 'store', 'update', 'destroy']);
        Route::post('loans/{loan}/repayments', [LoanController::class, 'storeRepayment'])
            ->name('loans.repayments.store');
        Route::delete('loans/{loan}/repayments/{repayment}', [LoanController::class, 'destroyRepayment'])
            ->name('loans.repayments.destroy');
        Route::delete('loans/{loan}/proofs/{mediaId}', [LoanController::class, 'destroyProof'])
            ->name('loans.proofs.destroy');

        Route::get('borrowings/search-income-transactions', [BorrowingController::class, 'searchIncomeTransactions'])
            ->name('borrowings.search-income-transactions');
        Route::resource('borrowings', BorrowingController::class)
            ->only(['index', 'store', 'update', 'destroy']);
        Route::post('borrowings/{borrowing}/repayments', [BorrowingController::class, 'storeRepayment'])
            ->name('borrowings.repayments.store');
        Route::delete('borrowings/{borrowing}/repayments/{repayment}', [BorrowingController::class, 'destroyRepayment'])
            ->name('borrowings.repayments.destroy');
        Route::delete('borrowings/{borrowing}/proofs/{mediaId}', [BorrowingController::class, 'destroyProof'])
            ->name('borrowings.proofs.destroy');

        // AI Assistant: Smart Input tracking + AI Advisor in one chat
        Route::middleware(EnsureAssistantEnabled::class)->prefix('assistant')->name('assistant.')->group(function () {
            Route::get('/', [AssistantController::class, 'index'])->name('index');
            Route::post('send', [AssistantController::class, 'send'])
                ->middleware('throttle:20,1')
                ->name('send');
            Route::post('messages/{message}/drafts/{index}/save', [AssistantController::class, 'saveDraft'])
                ->whereNumber(['message', 'index'])
                ->name('drafts.save');
            Route::post('confirm', [AssistantController::class, 'confirm'])->name('confirm');
            Route::patch('conversations/{conversationId}', [AssistantController::class, 'renameConversation'])
                ->name('conversations.rename');
            Route::delete('conversations/{conversationId}', [AssistantController::class, 'destroyConversation'])
                ->name('conversations.destroy');
        });

        // The Smart Input and AI Advisor pages were merged into the AI Assistant
        Route::redirect('smart-input', '/dashboard/finance/assistant');
        Route::redirect('advisor', '/dashboard/finance/assistant');

        // Smart Input History
        Route::get('smart-input-history', [SmartInputHistoryController::class, 'index'])
            ->name('smart-input-history.index');
        Route::delete('smart-input-history/{history}', [SmartInputHistoryController::class, 'destroy'])
            ->name('smart-input-history.destroy');
    });
