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
use Modules\Finance\Models\{Account, Category, Transaction};
use Modules\Finance\Services\{GenericCsvImportService, PayoneerImportService, TechcombankImportService, TechcombankPdfImportService};
use Modules\Finance\Services\TransactionService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private TransactionService $transactionService
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

        // Calculate totals for filtered transactions
        $totals = (clone $query)->selectRaw("
            SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END) as total_income,
            SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END) as total_expense,
            COUNT(*) as transaction_count
        ")->first();

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
            'totals' => [
                'income' => (float) ($totals->total_income ?? 0),
                'expense' => (float) ($totals->total_expense ?? 0),
                'net' => (float) (($totals->total_income ?? 0) - ($totals->total_expense ?? 0)),
                'count' => (int) ($totals->transaction_count ?? 0),
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
            $this->transactionService->recordTransfer($validated);
        } elseif ($validated['type'] === 'income') {
            $this->transactionService->recordIncome($validated);
        } else {
            $this->transactionService->recordExpense($validated);
        }

        return Redirect::back()->with('success', 'Transaction recorded successfully');
    }

    public function update(UpdateTransactionRequest $request, Transaction $transaction): RedirectResponse
    {
        if ($transaction->transfer_transaction_id || $transaction->transaction_type === 'transfer') {
            return Redirect::back()->with('error', 'Transfer transactions cannot be edited. Please delete and create a new transfer.');
        }

        $validated = $request->validated();

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

        return Redirect::back()->with('success', 'Transaction updated successfully');
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
            foreach ($transactions as $transaction) {
                // Handle linked transfer transactions
                if ($transaction->transfer_transaction_id) {
                    $linkedTransaction = Transaction::with('account')
                        ->find($transaction->transfer_transaction_id);

                    if ($linkedTransaction) {
                        if ($linkedTransaction->transaction_type === 'income') {
                            $linkedTransaction->account->decrement('current_balance', $linkedTransaction->amount);
                        } elseif ($linkedTransaction->transaction_type === 'expense') {
                            $linkedTransaction->account->increment('current_balance', $linkedTransaction->amount);
                        }
                        $linkedTransaction->delete();
                    }
                }

                // Reverse balance update
                if ($transaction->transaction_type === 'income') {
                    $transaction->account->decrement('current_balance', $transaction->amount);
                } elseif ($transaction->transaction_type === 'expense') {
                    $transaction->account->increment('current_balance', $transaction->amount);
                }

                $transaction->delete();
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

        [$first, $second] = $transactions->values();

        DB::transaction(function () use ($first, $second) {
            $first->update(['transfer_transaction_id' => $second->id]);
            $second->update(['transfer_transaction_id' => $first->id]);
        });

        return Redirect::back()->with('success', 'Transactions linked as transfer pair');
    }

    public function destroy(Transaction $transaction): RedirectResponse
    {
        $this->authorize('delete', $transaction);

        DB::transaction(function () use ($transaction) {
            if ($transaction->transfer_transaction_id) {
                $linkedTransaction = Transaction::find($transaction->transfer_transaction_id);

                if ($linkedTransaction) {
                    if ($linkedTransaction->type === 'income') {
                        $linkedTransaction->account->decrement('current_balance', $linkedTransaction->amount);
                    } elseif ($linkedTransaction->type === 'expense') {
                        $linkedTransaction->account->increment('current_balance', $linkedTransaction->amount);
                    }

                    $linkedTransaction->delete();
                }
            }

            if ($transaction->type === 'income') {
                $transaction->account->decrement('current_balance', $transaction->amount);
            } elseif ($transaction->type === 'expense') {
                $transaction->account->increment('current_balance', $transaction->amount);
            }

            $transaction->delete();
        });

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
            (int) $validated['amount']
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
}
