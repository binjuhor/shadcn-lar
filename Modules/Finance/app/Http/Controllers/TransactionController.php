<?php

namespace Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Finance\Http\Requests\Transaction\ConversionPreviewRequest;
use Modules\Finance\Http\Requests\Transaction\ExportTransactionRequest;
use Modules\Finance\Http\Requests\Transaction\IndexTransactionRequest;
use Modules\Finance\Http\Requests\Transaction\StoreTransactionRequest;
use Modules\Finance\Http\Requests\Transaction\UpdateTransactionRequest;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\Category;
use Modules\Finance\Models\Transaction;
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

        if ($request->account_id) {
            $query->where('account_id', $request->account_id);
        }

        if ($request->category_id) {
            $query->where('category_id', $request->category_id);
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
            $query->where('description', 'like', '%'.$request->search.'%');
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
        if ($transaction->transfer_transaction_id) {
            return Redirect::back()->with('error', 'Transfer transactions cannot be edited. Please delete and create a new transfer.');
        }

        $validated = $request->validated();

        DB::transaction(function () use ($transaction, $validated) {
            $oldAmount = (float) $transaction->amount;
            $newAmount = isset($validated['amount']) ? (float) $validated['amount'] : $oldAmount;

            if ($oldAmount !== $newAmount) {
                $difference = $newAmount - $oldAmount;

                if ($transaction->transaction_type === 'income') {
                    $transaction->account->increment('current_balance', $difference);
                } elseif ($transaction->transaction_type === 'expense') {
                    $transaction->account->decrement('current_balance', $difference);
                }
            }

            $transaction->update($validated);
        });

        return Redirect::back()->with('success', 'Transaction updated successfully');
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
}
