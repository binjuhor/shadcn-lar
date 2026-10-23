<?php

namespace Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\{JsonResponse, RedirectResponse};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Redirect};
use Inertia\{Inertia, Response};
use Modules\Finance\Http\Requests\Transaction\{
    BulkUpdateTransactionRequest,
    ConversionPreviewRequest,
    ExportTransactionRequest,
    ImportTransactionRequest,
    IndexTransactionRequest,
    StoreTransactionRequest,
    UpdateTransactionRequest
};
use Modules\Finance\Models\{Account, Category, Currency, Transaction};
use Modules\Finance\Services\{BillAttachmentService, BudgetService, ExchangeRateService, GenericCsvImportService, LoanService, PayoneerImportService, TechcombankImportService, TechcombankPdfImportService};
use Modules\Finance\Services\TransactionService;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private TransactionService $transactionService,
        private BillAttachmentService $billAttachmentService,
        private BudgetService $budgetService,
        private LoanService $loanService,
        private ExchangeRateService $exchangeRateService,
    ) {}

    public function index(IndexTransactionRequest $request): Response
    {
        $userId = auth()->id();

        $query = Transaction::with(['account', 'category', 'transferAccount'])
            ->whereHas('account', fn ($q) => $q->where('user_id', $userId));

        if ($request->account_ids && count($request->account_ids) > 0) {
            $query->whereIn('account_id', $request->account_ids);
        }

        if ($request->category_ids && count($request->category_ids) > 0) {
            $query->whereIn('category_id', $request->category_ids);
        }

        if ($request->type) {
            $query->where('transaction_type', $request->type);
        }

        if ($request->date_from) {
            $query->whereDate('transaction_date', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $query->whereDate('transaction_date', '<=', $request->date_to);
        }

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('description', 'like', '%'.$request->search.'%')
                    ->orWhere('notes', 'like', '%'.$request->search.'%');

                if (is_numeric($request->search)) {
                    $q->orWhere('amount', $request->search);
                }
            });
        }

        if ($request->amount_from) {
            $query->where('amount', '>=', $request->amount_from);
        }

        if ($request->amount_to) {
            $query->where('amount', '<=', $request->amount_to);
        }

        $user = auth()->user();
        $userSettings = $user->finance_settings ?? [];
        $defaultCode = $userSettings['default_currency'] ?? Currency::where('is_default', true)->first()?->code ?? 'VND';

        // Calculate totals for filtered transactions, grouped by currency
        $totalsRaw = (clone $query)
            ->whereNull('transfer_account_id')
            ->selectRaw('transaction_type, currency_code, SUM(amount) as total, COUNT(*) as cnt')
            ->groupBy('transaction_type', 'currency_code')
            ->get();

        // If all transactions share one currency, keep that currency (e.g. USD-only filter).
        // If mixed, convert everything to the user's default currency.
        $currencies = $totalsRaw->pluck('currency_code')->unique()->values();
        $totalsCurrency = $currencies->count() === 1 ? $currencies->first() : $defaultCode;

        $totalIncome = 0.0;
        $totalExpense = 0.0;
        $transactionCount = 0;
        $breakdown = ['income' => [], 'expense' => []];

        foreach ($totalsRaw as $row) {
            $amount = $this->convertToDefault((float) $row->total, $row->currency_code, $totalsCurrency);
            $type = $row->transaction_type;

            if ($type === 'income') {
                $totalIncome += $amount;
            } else {
                $totalExpense += $amount;
            }

            $transactionCount += (int) $row->cnt;

            // Track raw amounts per foreign currency for secondary display
            if ($row->currency_code !== $totalsCurrency) {
                $breakdown[$type][$row->currency_code] = ($breakdown[$type][$row->currency_code] ?? 0.0) + (float) $row->total;
            }
        }

        $transactions = $query->orderBy('transaction_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(50)
            ->withQueryString();

        $accounts = Account::where('user_id', $userId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $categories = Category::userCategories($userId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return Inertia::render('Finance::transactions/index', [
            'transactions' => $transactions,
            'accounts' => $accounts,
            'categories' => $categories,
            'filters' => $request->filters(),
            'defaultCurrency' => $defaultCode,
            'totals' => [
                'income' => $totalIncome,
                'expense' => $totalExpense,
                'net' => $totalIncome - $totalExpense,
                'count' => $transactionCount,
                'currency_code' => $totalsCurrency,
                'breakdown' => $breakdown,
            ],
        ]);
    }

    public function create(): Response
    {
        $userId = auth()->id();

        $accounts = Account::where('user_id', $userId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $categories = Category::userCategories($userId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return Inertia::render('Finance::transactions/create', [
            'accounts' => $accounts,
            'categories' => $categories,
        ]);
    }

    public function store(StoreTransactionRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        if ($validated['type'] === 'transfer') {
            $validated['from_account_id'] = $validated['account_id'];
            $validated['to_account_id'] = $validated['transfer_account_id'];
            // Both accounts must belong to the signed-in user.
            $validated['user_id'] = auth()->id();
            $result = $this->transactionService->recordTransfer($validated);
            // Attach bills to the debit transaction (the originating side of the transfer).
            $createdTransaction = $result['debit'] ?? null;
        } elseif ($validated['type'] === 'income') {
            $createdTransaction = $this->transactionService->recordIncome($validated);
        } else {
            $createdTransaction = $this->transactionService->recordExpense($validated);
        }

        if ($createdTransaction && $request->hasFile('bills')) {
            $this->billAttachmentService->attach($createdTransaction, 'bills', $request->file('bills'));
        }

        // Auto-create a loan record when the selected category is marked as a loan category
        if ($createdTransaction && ! empty($validated['category_id'])) {
            $category = Category::find($validated['category_id']);

            if ($category?->is_loan) {
                $account = Account::find($validated['account_id']);

                $this->loanService->createLoan([
                    'user_id' => auth()->id(),
                    'transaction_id' => $createdTransaction->id,
                    'borrower_name' => $validated['description'],
                    'amount' => $validated['amount'],
                    'currency_code' => $account?->currency_code ?? 'VND',
                    'loan_date' => $validated['transaction_date'],
                    'message' => $validated['notes'] ?? null,
                ]);
            }
        }

        return Redirect::back()->with('success', 'Transaction recorded successfully');
    }

    public function update(UpdateTransactionRequest $request, Transaction $transaction): RedirectResponse
    {
        $validated = $request->validated();

        if ($transaction->transfer_transaction_id) {
            return $this->updateTransferPair($request, $transaction, $validated);
        }

        $oldCategoryId = $transaction->category_id;
        $oldDate = $transaction->transaction_date->toDateString();
        $oldType = $transaction->transaction_type;

        DB::transaction(function () use ($transaction, $validated) {
            $oldAmount = (float) $transaction->amount;
            $newAmount = isset($validated['amount']) ? (float) $validated['amount'] : $oldAmount;
            $oldAccountId = $transaction->account_id;
            $newAccountId = isset($validated['account_id']) ? (int) $validated['account_id'] : $oldAccountId;
            $oldType = $transaction->transaction_type;
            $newType = $validated['transaction_type'] ?? $oldType;

            // Reverse old transaction impact
            if ($oldType === 'income') {
                $transaction->account->decrement('current_balance', $oldAmount);
            } elseif ($oldType === 'expense') {
                $transaction->account->increment('current_balance', $oldAmount);
            }

            // Apply new transaction impact on the appropriate account
            $targetAccount = $oldAccountId !== $newAccountId
                ? Account::find($newAccountId)
                : $transaction->account;

            if ($newType === 'income') {
                $targetAccount->increment('current_balance', $newAmount);
            } elseif ($newType === 'expense') {
                $targetAccount->decrement('current_balance', $newAmount);
            }

            // Build update data explicitly
            $updateData = [
                'account_id' => $newAccountId,
                'transaction_type' => $newType,
                'amount' => $newAmount,
                'description' => $validated['description'] ?? $transaction->description,
                'notes' => $validated['notes'] ?? $transaction->notes,
                'transaction_date' => $validated['transaction_date'] ?? $transaction->transaction_date,
            ];

            // Handle category: clear if type changed, otherwise use provided value
            if ($oldType !== $newType) {
                $updateData['category_id'] = null;
            } elseif (array_key_exists('category_id', $validated)) {
                $updateData['category_id'] = $validated['category_id'];
            }

            $transaction->update($updateData);
        });

        // Bill attachments live outside the DB transaction — uploads to remote storage
        // (R2) shouldn't roll back on a transient network blip after the txn already saved.
        if (! empty($validated['removed_bill_ids'])) {
            // Only delete media that actually belongs to this transaction's collection.
            // Stops a malicious request from passing arbitrary media IDs.
            $allowedIds = $transaction->getMedia('bills')->pluck('id')->all();
            $toRemove = array_intersect($validated['removed_bill_ids'], $allowedIds);
            if (! empty($toRemove)) {
                Media::whereIn('id', $toRemove)->each(fn (Media $m) => $m->delete());
            }
        }

        if ($request->hasFile('bills')) {
            $this->billAttachmentService->attach($transaction->fresh(), 'bills', $request->file('bills'));
        }

        // Sync budget spending for affected categories after update
        $transaction->refresh();
        $newCategoryId = $transaction->category_id;
        $newDate = $transaction->transaction_date->toDateString();
        $newType = $transaction->transaction_type;

        if ($oldType === 'expense' && $oldCategoryId) {
            $this->budgetService->syncBudgetsForCategory($transaction->user_id, $oldCategoryId, $oldDate);
        }

        if ($newType === 'expense' && $newCategoryId && $newCategoryId !== $oldCategoryId) {
            $this->budgetService->syncBudgetsForCategory($transaction->user_id, $newCategoryId, $newDate);
        }

        return Redirect::back()->with('success', 'Transaction updated successfully');
    }

    private function updateTransferPair(Request $request, Transaction $transaction, array $validated): RedirectResponse
    {
        $linkedTx = Transaction::findOrFail($transaction->transfer_transaction_id);

        // The outgoing side owns the source account; the incoming side owns the destination.
        // The legacy type fallback keeps edits working before the backfill command is run.
        if ($transaction->isOutgoingTransfer()) {
            $debitTx = $transaction;
            $creditTx = $linkedTx;
        } else {
            $debitTx = $linkedTx;
            $creditTx = $transaction;
        }

        $newFromAccountId = (int) ($validated['account_id'] ?? $debitTx->account_id);
        $newToAccountId = (int) ($validated['transfer_account_id'] ?? $creditTx->account_id);
        $newAmount = (float) ($validated['amount'] ?? $debitTx->amount);
        $newDate = $validated['transaction_date'] ?? $debitTx->transaction_date->toDateString();
        $newDescription = array_key_exists('description', $validated) ? $validated['description'] : null;
        $newNotes = array_key_exists('notes', $validated) ? $validated['notes'] : null;

        $newFromAccount = Account::findOrFail($newFromAccountId);
        $newToAccount = Account::findOrFail($newToAccountId);

        // Convert the credit (destination) side for cross-currency transfers so the
        // received amount reflects the exchange rate instead of the raw source amount.
        // Mirrors the conversion done when the transfer is first created.
        $creditAmount = $newAmount;

        if ($newFromAccount->currency_code !== $newToAccount->currency_code) {
            $exchangeRate = $this->exchangeRateService->getRate(
                $newFromAccount->currency_code,
                $newToAccount->currency_code,
                $newFromAccount->rate_source
            );

            if (! $exchangeRate) {
                return Redirect::back()->withErrors([
                    'amount' => "Exchange rate not found for {$newFromAccount->currency_code} to {$newToAccount->currency_code}",
                ]);
            }

            $creditAmount = (int) round($newAmount * $exchangeRate);
        }

        DB::transaction(function () use ($debitTx, $creditTx, $newFromAccount, $newToAccount, $newFromAccountId, $newToAccountId, $newAmount, $creditAmount, $newDate, $newDescription, $newNotes) {
            // Reverse old balance impacts
            $debitTx->account->increment('current_balance', $debitTx->amount);
            $creditTx->account->decrement('current_balance', $creditTx->amount);

            // Apply new balance impacts — destination receives the converted amount
            $newFromAccount->decrement('current_balance', $newAmount);
            $newToAccount->increment('current_balance', $creditAmount);

            $debitTx->update([
                'transaction_type' => 'transfer',
                'transfer_direction' => 'outgoing',
                'category_id' => null,
                'account_id' => $newFromAccountId,
                'transfer_account_id' => $newToAccountId,
                'amount' => $newAmount,
                'transaction_date' => $newDate,
                'description' => $newDescription ?? "Transfer to {$newToAccount->name}",
                'notes' => $newNotes,
                'currency_code' => $newFromAccount->currency_code,
            ]);

            $creditTx->update([
                'transaction_type' => 'transfer',
                'transfer_direction' => 'incoming',
                'category_id' => null,
                'account_id' => $newToAccountId,
                'transfer_account_id' => $newFromAccountId,
                'amount' => $creditAmount,
                'transaction_date' => $newDate,
                'description' => $newDescription ?? "Transfer from {$newFromAccount->name}",
                'notes' => $newNotes,
                'currency_code' => $newToAccount->currency_code,
            ]);
        });

        // Bill attachments — apply to whichever side the user edited
        if (! empty($validated['removed_bill_ids'])) {
            $allowedIds = $transaction->getMedia('bills')->pluck('id')->all();
            $toRemove = array_intersect($validated['removed_bill_ids'], $allowedIds);
            if (! empty($toRemove)) {
                Media::whereIn('id', $toRemove)->each(fn (Media $m) => $m->delete());
            }
        }

        if ($request->hasFile('bills')) {
            $this->billAttachmentService->attach($transaction->fresh(), 'bills', $request->file('bills'));
        }

        return Redirect::back()->with('success', 'Transfer updated successfully');
    }

    public function bulkUpdate(BulkUpdateTransactionRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $userId = auth()->id();

        $newType = $validated['transaction_type'] ?? null;

        $updateData = collect($validated)
            ->only(['account_id', 'category_id', 'transaction_date', 'transaction_type'])
            ->filter(fn ($value) => $value !== null)
            ->toArray();

        if (empty($updateData)) {
            return Redirect::back()->with('error', 'No fields to update');
        }

        $transactions = Transaction::with('account')
            ->whereIn('id', $validated['transaction_ids'])
            ->whereHas('account', fn ($q) => $q->where('user_id', $userId))
            ->whereNull('transfer_transaction_id')
            ->where('transaction_type', '!=', 'transfer')
            ->get();

        $updatedCount = 0;

        DB::transaction(function () use ($transactions, $updateData, $newType, $validated, &$updatedCount) {
            foreach ($transactions as $transaction) {
                // Handle type change with balance adjustments
                if ($newType && $newType !== $transaction->transaction_type) {
                    $amount = (float) $transaction->amount;

                    // Reverse old impact
                    if ($transaction->transaction_type === 'income') {
                        $transaction->account->decrement('current_balance', $amount);
                    } elseif ($transaction->transaction_type === 'expense') {
                        $transaction->account->increment('current_balance', $amount);
                    }

                    // Apply new impact
                    if ($newType === 'income') {
                        $transaction->account->increment('current_balance', $amount);
                    } elseif ($newType === 'expense') {
                        $transaction->account->decrement('current_balance', $amount);
                    }

                    // Clear category when type changes, unless user provided a new category
                    if (! array_key_exists('category_id', $validated) || $validated['category_id'] === null) {
                        $updateData['category_id'] = null;
                    }
                }

                $transaction->update($updateData);
                $updatedCount++;
            }
        });

        $skippedCount = count($validated['transaction_ids']) - $updatedCount;
        $message = "{$updatedCount} transaction(s) updated";

        if ($skippedCount > 0) {
            $message .= " ({$skippedCount} transfer transaction(s) skipped)";
        }

        return Redirect::back()->with('success', $message);
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'transaction_ids' => ['required', 'array', 'min:1'],
            'transaction_ids.*' => ['exists:finance_transactions,id'],
        ]);

        $userId = auth()->id();

        $transactions = Transaction::with('account')
            ->whereIn('id', $validated['transaction_ids'])
            ->whereHas('account', fn ($q) => $q->where('user_id', $userId))
            ->get();

        $deletedCount = 0;

        DB::transaction(function () use ($transactions, &$deletedCount) {
            $processedIds = [];

            foreach ($transactions as $transaction) {
                if (in_array($transaction->id, $processedIds, true)) {
                    continue;
                }

                // Handle linked transfer transactions
                if ($transaction->transfer_transaction_id) {
                    $linkedTransaction = Transaction::with('account')
                        ->find($transaction->transfer_transaction_id);

                    if ($linkedTransaction) {
                        $reverseImpact = -$linkedTransaction->balanceImpact();
                        if ($reverseImpact !== 0.0) {
                            $linkedTransaction->account->increment('current_balance', $reverseImpact);
                        }
                        $linkedTransaction->delete();
                        $processedIds[] = $linkedTransaction->id;
                    }
                }

                // Reverse balance update
                $reverseImpact = -$transaction->balanceImpact();
                if ($reverseImpact !== 0.0) {
                    $transaction->account->increment('current_balance', $reverseImpact);
                }

                $transaction->delete();
                $processedIds[] = $transaction->id;
                $deletedCount++;
            }
        });

        return Redirect::back()->with('success', "{$deletedCount} transaction(s) deleted");
    }

    public function linkAsTransfer(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'transaction_ids' => ['required', 'array', 'size:2'],
            'transaction_ids.*' => ['exists:finance_transactions,id'],
        ]);

        $userId = auth()->id();

        $transactions = Transaction::whereIn('id', $validated['transaction_ids'])
            ->whereHas('account', fn ($q) => $q->where('user_id', $userId))
            ->get();

        if ($transactions->count() !== 2) {
            return Redirect::back()->with('error', 'Both transactions must belong to you');
        }

        // Check neither is already linked
        if ($transactions->whereNotNull('transfer_transaction_id')->isNotEmpty()) {
            return Redirect::back()->with('error', 'One or both transactions are already linked');
        }

        $outgoing = $transactions->firstWhere('transaction_type', 'expense');
        $incoming = $transactions->firstWhere('transaction_type', 'income');

        if (! $outgoing || ! $incoming) {
            return Redirect::back()->with('error', 'A transfer pair must contain one expense and one income transaction');
        }

        DB::transaction(function () use ($outgoing, $incoming) {
            $outgoing->update([
                'transaction_type' => 'transfer',
                'transfer_direction' => 'outgoing',
                'category_id' => null,
                'transfer_account_id' => $incoming->account_id,
                'transfer_transaction_id' => $incoming->id,
            ]);
            $incoming->update([
                'transaction_type' => 'transfer',
                'transfer_direction' => 'incoming',
                'category_id' => null,
                'transfer_account_id' => $outgoing->account_id,
                'transfer_transaction_id' => $outgoing->id,
            ]);
        });

        return Redirect::back()->with('success', 'Transactions linked as transfer pair');
    }

    public function destroy(Transaction $transaction): RedirectResponse
    {
        $this->authorize('delete', $transaction);

        $categoryId = $transaction->category_id;
        $userId = $transaction->user_id;
        $date = $transaction->transaction_date->toDateString();
        $type = $transaction->transaction_type;

        DB::transaction(function () use ($transaction) {
            if ($transaction->transfer_transaction_id) {
                $linkedTransaction = Transaction::find($transaction->transfer_transaction_id);

                if ($linkedTransaction) {
                    $reverseImpact = -$linkedTransaction->balanceImpact();
                    if ($reverseImpact !== 0.0) {
                        $linkedTransaction->account->increment('current_balance', $reverseImpact);
                    }

                    $linkedTransaction->delete();
                }
            }

            $reverseImpact = -$transaction->balanceImpact();
            if ($reverseImpact !== 0.0) {
                $transaction->account->increment('current_balance', $reverseImpact);
            }

            $transaction->delete();
        });

        if ($type === 'expense' && $categoryId) {
            $this->budgetService->syncBudgetsForCategory($userId, $categoryId, $date);
        }

        return Redirect::back()->with('success', 'Transaction deleted successfully');
    }

    public function reconcile(Transaction $transaction): RedirectResponse
    {
        $this->authorize('update', $transaction);

        $this->transactionService->reconcileTransaction($transaction->id);

        return Redirect::back()->with('success', 'Transaction reconciled');
    }

    public function unreconcile(Transaction $transaction): RedirectResponse
    {
        $this->authorize('update', $transaction);

        $transaction->update(['reconciled_at' => null]);

        return Redirect::back()->with('success', 'Transaction unreconciled');
    }

    public function conversionPreview(ConversionPreviewRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $preview = $this->transactionService->getTransferConversionPreview(
            $validated['from_account_id'],
            $validated['to_account_id'],
            (float) $validated['amount']
        );

        return response()->json($preview);
    }

    public function export(ExportTransactionRequest $request): StreamedResponse
    {
        $validated = $request->validated();
        $userId = auth()->id();

        $query = Transaction::with(['account', 'category'])
            ->whereHas('account', fn ($q) => $q->where('user_id', $userId));

        $period = $validated['period'];
        $filename = 'transactions';

        if ($period === 'custom') {
            $query->whereDate('transaction_date', '>=', $validated['date_from'])
                ->whereDate('transaction_date', '<=', $validated['date_to']);
            $filename .= "_{$validated['date_from']}_to_{$validated['date_to']}";
        } elseif ($period === 'month') {
            $yearMonth = $validated['month'];
            [$year, $month] = explode('-', $yearMonth);
            $query->whereYear('transaction_date', $year)
                ->whereMonth('transaction_date', $month);
            $filename .= "_{$yearMonth}";
        } elseif ($period === 'year') {
            $year = $validated['year'];
            $query->whereYear('transaction_date', $year);
            $filename .= "_{$year}";
        }

        // Apply page filters
        if (! empty($validated['account_ids'])) {
            $query->whereIn('account_id', $validated['account_ids']);
        }

        if (! empty($validated['category_ids'])) {
            $query->whereIn('category_id', $validated['category_ids']);
        }

        if (! empty($validated['type'])) {
            $query->where('transaction_type', $validated['type']);
        }

        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");

                if (is_numeric($search)) {
                    $q->orWhere('amount', $search);
                }
            });
        }

        if (! empty($validated['amount_from'])) {
            $query->where('amount', '>=', $validated['amount_from']);
        }

        if (! empty($validated['amount_to'])) {
            $query->where('amount', '<=', $validated['amount_to']);
        }

        $transactions = $query->orderBy('transaction_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        $format = $validated['format'];
        $extension = $format === 'excel' ? 'xlsx' : 'csv';
        $filename .= ".{$extension}";

        if ($format === 'excel') {
            return $this->exportExcel($transactions, $filename);
        }

        return $this->exportCsv($transactions, $filename);
    }

    protected function exportCsv($transactions, string $filename): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(function () use ($transactions) {
            $handle = fopen('php://output', 'w');

            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                'Date',
                'Type',
                'Description',
                'Category',
                'Account',
                'Amount',
                'Currency',
                'Notes',
            ]);

            foreach ($transactions as $transaction) {
                fputcsv($handle, [
                    $transaction->transaction_date->format('Y-m-d'),
                    ucfirst($transaction->transaction_type),
                    $transaction->description,
                    $transaction->category?->name ?? '',
                    $transaction->account?->name ?? '',
                    $transaction->amount,
                    $transaction->currency_code,
                    $transaction->notes ?? '',
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    protected function exportExcel($transactions, string $filename): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'application/vnd.ms-excel',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(function () use ($transactions) {
            echo '<?xml version="1.0" encoding="UTF-8"?>';
            echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
            echo '<Worksheet ss:Name="Transactions"><Table>';

            echo '<Row>';
            foreach (['Date', 'Type', 'Description', 'Category', 'Account', 'Amount', 'Currency', 'Notes'] as $header) {
                echo "<Cell><Data ss:Type=\"String\">{$header}</Data></Cell>";
            }
            echo '</Row>';

            foreach ($transactions as $transaction) {
                echo '<Row>';
                echo '<Cell><Data ss:Type="String">'.$transaction->transaction_date->format('Y-m-d').'</Data></Cell>';
                echo '<Cell><Data ss:Type="String">'.ucfirst($transaction->transaction_type).'</Data></Cell>';
                echo '<Cell><Data ss:Type="String">'.htmlspecialchars($transaction->description).'</Data></Cell>';
                echo '<Cell><Data ss:Type="String">'.htmlspecialchars($transaction->category?->name ?? '').'</Data></Cell>';
                echo '<Cell><Data ss:Type="String">'.htmlspecialchars($transaction->account?->name ?? '').'</Data></Cell>';
                echo '<Cell><Data ss:Type="Number">'.$transaction->amount.'</Data></Cell>';
                echo '<Cell><Data ss:Type="String">'.$transaction->currency_code.'</Data></Cell>';
                echo '<Cell><Data ss:Type="String">'.htmlspecialchars($transaction->notes ?? '').'</Data></Cell>';
                echo '</Row>';
            }

            echo '</Table></Worksheet></Workbook>';
        }, 200, $headers);
    }

    public function import(): Response
    {
        $userId = auth()->id();

        $accounts = Account::where('user_id', $userId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $categories = Category::userCategories($userId)
            ->where('is_active', true)
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        return Inertia::render('Finance::transactions/import', [
            'accounts' => $accounts,
            'categories' => $categories,
        ]);
    }

    public function importPreview(
        Request $request,
        PayoneerImportService $payoneerService,
        TechcombankImportService $techcombankService,
        GenericCsvImportService $genericCsvService,
        TechcombankPdfImportService $techcombankPdfService
    ): JsonResponse {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls,pdf', 'max:10240'],
            'source' => ['required', 'in:payoneer,techcombank,techcombank_pdf,generic'],
        ]);

        $file = $request->file('file');
        $source = $request->input('source');

        $transactions = match ($source) {
            'techcombank' => $techcombankService->parseExcel($file->getPathname()),
            'techcombank_pdf' => $techcombankPdfService->parsePdf($file->getPathname()),
            'generic' => $genericCsvService->parseCSV($file->getPathname()),
            default => $payoneerService->parseCSV($file->getPathname()),
        };

        // Get unique suggested categories
        $suggestedCategories = $transactions
            ->pluck('suggested_category')
            ->filter()
            ->unique()
            ->values();

        return response()->json([
            'transactions' => $transactions->values(),
            'summary' => [
                'total' => $transactions->count(),
                'income' => $transactions->where('type', 'income')->count(),
                'expense' => $transactions->where('type', 'expense')->count(),
                'total_income' => $transactions->where('type', 'income')->sum('amount'),
                'total_expense' => $transactions->where('type', 'expense')->sum('amount'),
                'date_range' => [
                    'from' => $transactions->min('transaction_date'),
                    'to' => $transactions->max('transaction_date'),
                ],
            ],
            'suggested_categories' => $suggestedCategories,
        ]);
    }

    public function importStore(
        ImportTransactionRequest $request,
        PayoneerImportService $payoneerService,
        TechcombankImportService $techcombankService,
        GenericCsvImportService $genericCsvService,
        TechcombankPdfImportService $techcombankPdfService
    ): RedirectResponse {
        $validated = $request->validated();
        $userId = auth()->id();
        $source = $validated['source'] ?? 'payoneer';

        $file = $request->file('file');

        if ($source === 'techcombank') {
            $transactions = $techcombankService->parseExcel($file->getPathname());
            $result = $techcombankService->importTransactions(
                $transactions,
                $validated['account_id'],
                $userId,
                $validated['category_mappings'] ?? [],
                $validated['skip_duplicates'] ?? true
            );
        } elseif ($source === 'techcombank_pdf') {
            $transactions = $techcombankPdfService->parsePdf($file->getPathname());
            $result = $techcombankPdfService->importTransactions(
                $transactions,
                $validated['account_id'],
                $userId,
                $validated['category_mappings'] ?? [],
                $validated['skip_duplicates'] ?? true
            );
        } elseif ($source === 'generic') {
            $transactions = $genericCsvService->parseCSV($file->getPathname());
            $result = $genericCsvService->importTransactions(
                $transactions,
                $validated['account_id'],
                $userId,
                $validated['category_mappings'] ?? [],
                $validated['skip_duplicates'] ?? true
            );
        } else {
            $transactions = $payoneerService->parseCSV($file->getPathname());
            $result = $payoneerService->importTransactions(
                $transactions,
                $validated['account_id'],
                $userId,
                $validated['category_mappings'] ?? [],
                $validated['skip_duplicates'] ?? true
            );
        }

        $message = "Imported {$result['imported']} transactions";
        if ($result['skipped'] > 0) {
            $message .= ", skipped {$result['skipped']} duplicates";
        }
        if (count($result['errors']) > 0) {
            $message .= ', '.count($result['errors']).' errors';
        }

        return Redirect::route('dashboard.finance.transactions.index')
            ->with('success', $message);
    }

    private function convertToDefault(float $amount, string $fromCurrency, string $defaultCurrency): float
    {
        if ($fromCurrency === $defaultCurrency) {
            return $amount;
        }

        try {
            return $this->exchangeRateService->convert($amount, $fromCurrency, $defaultCurrency);
        } catch (\Exception) {
            return $amount;
        }
    }
}
