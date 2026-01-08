<?php

namespace Modules\Finance\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Modules\Finance\Http\Resources\TransactionResource;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\Transaction;
use Modules\Finance\Services\TransactionService;

class TransactionApiController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected TransactionService $transactionService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $userId = auth()->id();

        $query = Transaction::with(['account', 'category', 'transferAccount'])
            ->whereHas('account', fn ($q) => $q->where('user_id', $userId));

        if ($request->has('account_id')) {
            $query->where('account_id', $request->account_id);
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('type')) {
            $query->where('transaction_type', $request->type);
        }

        if ($request->has('date_from')) {
            $query->whereDate('transaction_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('transaction_date', '<=', $request->date_to);
        }

        if ($request->has('search')) {
            $query->where('description', 'like', '%'.$request->search.'%');
        }

        if ($request->boolean('reconciled_only', false)) {
            $query->whereNotNull('reconciled_at');
        }

        if ($request->boolean('unreconciled_only', false)) {
            $query->whereNull('reconciled_at');
        }

        $perPage = min($request->integer('per_page', 50), 100);

        $transactions = $query->orderBy('transaction_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return TransactionResource::collection($transactions);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:income,expense,transfer'],
            'account_id' => ['required', 'exists:finance_accounts,id'],
            'category_id' => ['nullable', 'exists:finance_categories,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'transaction_date' => ['required', 'date'],
            'transfer_account_id' => ['nullable', 'exists:finance_accounts,id', 'different:account_id'],
        ]);

        $account = Account::findOrFail($validated['account_id']);
        $this->authorize('view', $account);

        try {
            if ($validated['type'] === 'transfer') {
                if (! $validated['transfer_account_id']) {
                    return response()->json([
                        'message' => 'Transfer account is required for transfer transactions',
                    ], 422);
                }

                $result = $this->transactionService->recordTransfer([
                    'from_account_id' => $validated['account_id'],
                    'to_account_id' => $validated['transfer_account_id'],
                    'amount' => $validated['amount'],
                    'description' => $validated['description'] ?? null,
                    'transaction_date' => $validated['transaction_date'],
                ]);

                $result['debit']->load(['account', 'category', 'transferAccount']);

                return response()->json([
                    'message' => 'Transfer recorded successfully',
                    'data' => new TransactionResource($result['debit']),
                    'exchange_rate' => $result['exchange_rate'],
                    'converted_amount' => $result['converted_amount'],
                ], 201);
            }

            if ($validated['type'] === 'income') {
                $transaction = $this->transactionService->recordIncome($validated);
            } else {
                $transaction = $this->transactionService->recordExpense($validated);
            }

            $transaction->load(['account', 'category']);

            return response()->json([
                'message' => 'Transaction recorded successfully',
                'data' => new TransactionResource($transaction),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function show(Transaction $transaction): JsonResponse
    {
        $this->authorize('view', $transaction);

        $transaction->load(['account', 'category', 'transferAccount']);

        return response()->json([
            'data' => new TransactionResource($transaction),
        ]);
    }

    public function update(Request $request, Transaction $transaction): JsonResponse
    {
        $this->authorize('update', $transaction);

        // Transfer transactions cannot be edited - delete and recreate instead
        if ($transaction->transfer_transaction_id) {
            return response()->json([
                'message' => 'Transfer transactions cannot be edited. Please delete and create a new transfer.',
            ], 422);
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
                    $transaction->account->updateBalance($difference);
                } elseif ($transaction->transaction_type === 'expense') {
                    $transaction->account->updateBalance(-$difference);
                }
            }

            $transaction->update($validated);
        });

        $transaction->refresh();
        $transaction->load(['account', 'category']);

        return response()->json([
            'message' => 'Transaction updated successfully',
            'data' => new TransactionResource($transaction),
        ]);
    }

    public function destroy(Transaction $transaction): JsonResponse
    {
        $this->authorize('delete', $transaction);

        DB::transaction(function () use ($transaction) {
            if ($transaction->transfer_transaction_id) {
                $linkedTransaction = Transaction::find($transaction->transfer_transaction_id);

                if ($linkedTransaction) {
                    if ($linkedTransaction->transaction_type === 'income') {
                        $linkedTransaction->account->updateBalance(-$linkedTransaction->amount);
                    } elseif ($linkedTransaction->transaction_type === 'expense') {
                        $linkedTransaction->account->updateBalance($linkedTransaction->amount);
                    }

                    $linkedTransaction->delete();
                }
            }

            if ($transaction->transaction_type === 'income') {
                $transaction->account->updateBalance(-$transaction->amount);
            } elseif ($transaction->transaction_type === 'expense') {
                $transaction->account->updateBalance($transaction->amount);
            }

            $transaction->delete();
        });

        return response()->json([
            'message' => 'Transaction deleted successfully',
        ]);
    }

    public function reconcile(Transaction $transaction): JsonResponse
    {
        $this->authorize('update', $transaction);

        try {
            $this->transactionService->reconcileTransaction($transaction->id);

            $transaction->refresh();
            $transaction->load(['account', 'category']);

            return response()->json([
                'message' => 'Transaction reconciled successfully',
                'data' => new TransactionResource($transaction),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function unreconcile(Transaction $transaction): JsonResponse
    {
        $this->authorize('update', $transaction);

        $transaction->update(['reconciled_at' => null]);

        $transaction->load(['account', 'category']);

        return response()->json([
            'message' => 'Transaction unreconciled successfully',
            'data' => new TransactionResource($transaction),
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $userId = auth()->id();

        $query = Transaction::whereHas('account', fn ($q) => $q->where('user_id', $userId));

        if ($request->has('date_from')) {
            $query->whereDate('transaction_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('transaction_date', '<=', $request->date_to);
        }

        if ($request->has('account_id')) {
            $query->where('account_id', $request->account_id);
        }

        $totalIncome = (clone $query)->where('transaction_type', 'income')->sum('amount');
        $totalExpense = (clone $query)->where('transaction_type', 'expense')->sum('amount');
        $transactionCount = $query->count();

        return response()->json([
            'data' => [
                'total_income' => (float) $totalIncome,
                'total_expense' => (float) $totalExpense,
                'net' => (float) ($totalIncome - $totalExpense),
                'transaction_count' => $transactionCount,
            ],
        ]);
    }
}
