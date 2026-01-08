<?php

namespace Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;
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

    public function index(Request $request): Response
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
            'filters' => $request->only(['account_id', 'category_id', 'type', 'date_from', 'date_to', 'search']),
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

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:income,expense,transfer'],
            'account_id' => ['required', 'exists:finance_accounts,id'],
            'category_id' => ['nullable', 'exists:finance_categories,id'],
            'amount' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'transaction_date' => ['required', 'date'],
            'transfer_account_id' => ['nullable', 'exists:finance_accounts,id', 'different:account_id'],
        ]);

        $account = Account::findOrFail($validated['account_id']);
        $this->authorize('view', $account);

        if ($validated['type'] === 'transfer') {
            $validated['from_account_id'] = $validated['account_id'];
            $validated['to_account_id'] = $validated['transfer_account_id'];
            $this->transactionService->recordTransfer($validated);
        } elseif ($validated['type'] === 'income') {
            $this->transactionService->recordIncome($validated);
        } else {
            $this->transactionService->recordExpense($validated);
        }

        return Redirect::route('dashboard.finance.transactions.index')
            ->with('success', 'Transaction recorded successfully');
    }

    public function update(Request $request, Transaction $transaction): RedirectResponse
    {
        $this->authorize('update', $transaction);

        // Transfer transactions cannot be edited
        if ($transaction->transfer_transaction_id) {
            return Redirect::back()->with('error', 'Transfer transactions cannot be edited. Please delete and create a new transfer.');
        }

        $validated = $request->validate([
            'category_id' => ['nullable', 'exists:finance_categories,id'],
            'amount' => ['sometimes', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'transaction_date' => ['sometimes', 'date'],
        ]);

        DB::transaction(function () use ($transaction, $validated) {
            $oldAmount = (float) $transaction->amount;
            $newAmount = isset($validated['amount']) ? (float) $validated['amount'] : $oldAmount;

            // Adjust account balance if amount changed
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
            // Handle linked transfer transaction first
            if ($transaction->transfer_transaction_id) {
                $linkedTransaction = Transaction::find($transaction->transfer_transaction_id);

                if ($linkedTransaction) {
                    // Revert linked transaction's balance using its own amount
                    if ($linkedTransaction->type === 'income') {
                        $linkedTransaction->account->decrement('current_balance', $linkedTransaction->amount);
                    } elseif ($linkedTransaction->type === 'expense') {
                        $linkedTransaction->account->increment('current_balance', $linkedTransaction->amount);
                    }

                    $linkedTransaction->delete();
                }
            }

            // Revert this transaction's balance
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

    /**
     * Get conversion preview for cross-currency transfer
     */
    public function conversionPreview(Request $request)
    {
        $validated = $request->validate([
            'from_account_id' => ['required', 'exists:finance_accounts,id'],
            'to_account_id' => ['required', 'exists:finance_accounts,id'],
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        $fromAccount = Account::where('id', $validated['from_account_id'])
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $toAccount = Account::where('id', $validated['to_account_id'])
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $preview = $this->transactionService->getTransferConversionPreview(
            $fromAccount->id,
            $toAccount->id,
            (int) $validated['amount']
        );

        return response()->json($preview);
    }

    public function export(Request $request): StreamedResponse
    {
        $request->validate([
            'format' => ['required', 'in:csv,excel'],
            'period' => ['required', 'in:custom,month,year'],
            'date_from' => ['required_if:period,custom', 'nullable', 'date'],
            'date_to' => ['required_if:period,custom', 'nullable', 'date'],
            'month' => ['required_if:period,month', 'nullable', 'date_format:Y-m'],
            'year' => ['required_if:period,year', 'nullable', 'digits:4'],
        ]);

        $userId = auth()->id();
        $query = Transaction::with(['account', 'category'])
            ->whereHas('account', fn ($q) => $q->where('user_id', $userId));

        $period = $request->input('period');
        $filename = 'transactions';

        if ($period === 'custom') {
            $query->whereDate('transaction_date', '>=', $request->date_from)
                ->whereDate('transaction_date', '<=', $request->date_to);
            $filename .= "_{$request->date_from}_to_{$request->date_to}";
        } elseif ($period === 'month') {
            $yearMonth = $request->input('month');
            [$year, $month] = explode('-', $yearMonth);
            $query->whereYear('transaction_date', $year)
                ->whereMonth('transaction_date', $month);
            $filename .= "_{$yearMonth}";
        } elseif ($period === 'year') {
            $year = $request->input('year');
            $query->whereYear('transaction_date', $year);
            $filename .= "_{$year}";
        }

        $transactions = $query->orderBy('transaction_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        $format = $request->input('format');
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

            // BOM for Excel UTF-8 compatibility
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // Header row
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
        // Simple XML spreadsheet format (opens in Excel)
        $headers = [
            'Content-Type' => 'application/vnd.ms-excel',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(function () use ($transactions) {
            echo '<?xml version="1.0" encoding="UTF-8"?>';
            echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
            echo '<Worksheet ss:Name="Transactions"><Table>';

            // Header row
            echo '<Row>';
            foreach (['Date', 'Type', 'Description', 'Category', 'Account', 'Amount', 'Currency', 'Notes'] as $header) {
                echo "<Cell><Data ss:Type=\"String\">{$header}</Data></Cell>";
            }
            echo '</Row>';

            // Data rows
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
