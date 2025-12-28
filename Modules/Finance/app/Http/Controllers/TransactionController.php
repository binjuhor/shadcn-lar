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
            $query->where('type', $request->type);
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

    public function destroy(Transaction $transaction): RedirectResponse
    {
        $this->authorize('delete', $transaction);

        DB::transaction(function () use ($transaction) {
            if ($transaction->type === 'income') {
                $transaction->account->decrement('balance', $transaction->amount);
            } elseif ($transaction->type === 'expense') {
                $transaction->account->increment('balance', $transaction->amount);
            } elseif ($transaction->type === 'transfer' && $transaction->transfer_account_id) {
                $transaction->account->increment('balance', $transaction->amount);
                Account::find($transaction->transfer_account_id)?->decrement('balance', $transaction->amount);
            }

            if ($transaction->transfer_transaction_id) {
                Transaction::find($transaction->transfer_transaction_id)?->delete();
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
}
