<?php

namespace Modules\Finance\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Finance\Http\Resources\TransactionResource;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\Transaction;
use Modules\Finance\Services\BillAttachmentService;
use Modules\Finance\Services\TransactionService;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionApiController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected TransactionService $transactionService,
        protected BillAttachmentService $billAttachmentService,
    ) {}

    /**
     * Export the authenticated user's transactions as CSV.
     *
     * @queryParam from date Optional start date (Y-m-d). Example: 2026-01-01
     * @queryParam to date Optional end date (Y-m-d). Example: 2026-12-31
     */
    public function export(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $userId = auth()->id();

        $query = Transaction::without('media')
            ->with(['account', 'category'])
            ->whereHas('account', fn ($q) => $q->where('user_id', $userId));

        if (! empty($validated['from'])) {
            $query->whereDate('transaction_date', '>=', $validated['from']);
        }

        if (! empty($validated['to'])) {
            $query->whereDate('transaction_date', '<=', $validated['to']);
        }

        $filename = 'transactions';
        if (! empty($validated['from']) || ! empty($validated['to'])) {
            $filename .= '_'.($validated['from'] ?? 'start').'_to_'.($validated['to'] ?? 'now');
        }

        $query->orderBy('transaction_date')->orderBy('id');

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM so Excel renders non-ASCII text correctly (matches the web export).
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['date', 'type', 'amount', 'currency', 'account', 'category', 'description']);

            foreach ($query->lazy(500) as $transaction) {
                fputcsv($handle, array_map([$this, 'csvSafe'], [
                    $transaction->transaction_date->format('Y-m-d'),
                    $transaction->transaction_type,
                    $transaction->amount,
                    $transaction->currency_code,
                    $transaction->account?->name ?? '',
                    $transaction->category?->name ?? '',
                    $transaction->description ?? '',
                ]));
            }

            fclose($handle);
        }, "{$filename}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Neutralise spreadsheet formula injection in user-controlled text. */
    private function csvSafe(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'type' => ['sometimes', 'string', 'in:income,expense,transfer'],
            'account_id' => ['sometimes', 'integer'],
            'category_id' => ['sometimes', 'integer'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date'],
            'search' => ['sometimes', 'string', 'max:255'],
        ]);

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
            $query->where(function ($q) use ($request) {
                $q->where('description', 'like', '%'.$request->search.'%')
                    ->orWhere('notes', 'like', '%'.$request->search.'%');

                if (is_numeric($request->search)) {
                    $q->orWhere('amount', $request->search);
                }
            });
        }

        if ($request->boolean('reconciled_only', false)) {
            $query->whereNotNull('reconciled_at');
        }

        if ($request->boolean('unreconciled_only', false)) {
            $query->whereNull('reconciled_at');
        }

        $perPage = min($request->integer('per_page', 50), 100);

        $orderedQuery = $query->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc'); // stable secondary sort for cursor pagination

        // Always cursor-paginate so mobile clients get consistent meta (next_cursor / prev_cursor).
        $transactions = $orderedQuery->cursorPaginate($perPage);

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
            'bills' => ['nullable', 'array', 'max:10'],
            'bills.*' => ['file', 'extensions:jpg,jpeg,png,webp,heic,heif,pdf', 'max:10240'],
        ]);
        unset($validated['bills']);

        $account = Account::findOrFail($validated['account_id']);
        $this->authorize('view', $account);

        try {
            if ($validated['type'] === 'transfer') {
                if (! ($validated['transfer_account_id'] ?? null)) {
                    return response()->json([
                        'message' => 'Transfer account is required for transfer transactions',
                    ], 422);
                }

                $result = $this->transactionService->recordTransfer([
                    // Both accounts must belong to the signed-in user.
                    'user_id' => auth()->id(),
                    'from_account_id' => $validated['account_id'],
                    'to_account_id' => $validated['transfer_account_id'],
                    'amount' => $validated['amount'],
                    'description' => $validated['description'] ?? null,
                    'notes' => $validated['notes'] ?? null,
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

            // One unit: a failed receipt upload rolls the entry back, so a client retry cannot create a duplicate.
            $transaction = DB::transaction(function () use ($validated, $request) {
                $transaction = $validated['type'] === 'income'
                    ? $this->transactionService->recordIncome($validated)
                    : $this->transactionService->recordExpense($validated);

                if ($request->hasFile('bills')) {
                    $this->billAttachmentService->attach($transaction, 'bills', $request->file('bills'));
                }

                return $transaction;
            });

            $transaction->load(['account', 'category', 'media']);

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

        // Transfer transactions cannot be edited — delete and recreate instead
        if ($transaction->transfer_transaction_id) {
            return response()->json([
                'message' => 'Transfer transactions cannot be edited. Please delete and create a new transfer.',
            ], 422);
        }

        $validated = $request->validate([
            'type' => ['sometimes', 'in:income,expense'],
            'account_id' => ['sometimes', 'exists:finance_accounts,id'],
            'category_id' => ['nullable', 'exists:finance_categories,id'],
            'amount' => ['sometimes', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'transaction_date' => ['sometimes', 'date'],
            'bills' => ['nullable', 'array', 'max:10'],
            // extensions: is more reliable than mimes: for HEIC, which servers often report as octet-stream.
            'bills.*' => ['file', 'extensions:jpg,jpeg,png,webp,heic,heif,pdf', 'max:10240'],
            'removed_bill_ids' => ['nullable', 'array'],
            'removed_bill_ids.*' => ['integer'],
        ]);

        DB::transaction(function () use ($transaction, $validated) {
            $oldAmount = (float) $transaction->amount;
            $oldType = $transaction->transaction_type;
            $oldAccountId = $transaction->account_id;

            $newAmount = isset($validated['amount']) ? (float) $validated['amount'] : $oldAmount;
            $newType = isset($validated['type']) ? $validated['type'] : $oldType;
            $newAccountId = isset($validated['account_id']) ? (int) $validated['account_id'] : $oldAccountId;

            // Reverse the old balance impact (updateBalance also syncs savings goals)
            if ($oldType === 'income') {
                $transaction->account->updateBalance(-$oldAmount);
            } elseif ($oldType === 'expense') {
                $transaction->account->updateBalance($oldAmount);
            }

            // Apply the new impact on the target account (may differ from old)
            $targetAccount = ($newAccountId !== $oldAccountId)
                ? Account::findOrFail($newAccountId)
                : $transaction->account->fresh();

            if ($newType === 'income') {
                $targetAccount->updateBalance($newAmount);
            } elseif ($newType === 'expense') {
                $targetAccount->updateBalance(-$newAmount);
            }

            $updateData = [
                'transaction_type' => $newType,
                'account_id' => $newAccountId,
                'amount' => $newAmount,
                'description' => $validated['description'] ?? $transaction->description,
                // array_key_exists so an explicit null clears the note.
                'notes' => array_key_exists('notes', $validated) ? $validated['notes'] : $transaction->notes,
                'transaction_date' => $validated['transaction_date'] ?? $transaction->transaction_date,
            ];

            // Clear category when type changes (income/expense categories differ)
            if ($oldType !== $newType) {
                $updateData['category_id'] = null;
            } elseif (array_key_exists('category_id', $validated)) {
                $updateData['category_id'] = $validated['category_id'];
            }

            $transaction->update($updateData);
        });

        // Attachments run outside the DB transaction: a remote-storage hiccup must not roll back the saved edit.
        if (! empty($validated['removed_bill_ids'])) {
            // Only media in this transaction's own collection may be removed.
            $allowedIds = $transaction->getMedia('bills')->pluck('id')->all();
            $toRemove = array_intersect($validated['removed_bill_ids'], $allowedIds);
            if (! empty($toRemove)) {
                Media::whereIn('id', $toRemove)->each(fn (Media $media) => $media->delete());
            }
        }

        if ($request->hasFile('bills')) {
            $this->billAttachmentService->attach($transaction->fresh(), 'bills', $request->file('bills'));
        }

        $transaction->refresh();
        $transaction->load(['account', 'category', 'media']);

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
                    $reverseImpact = -$linkedTransaction->balanceImpact();
                    if ($reverseImpact !== 0.0) {
                        $linkedTransaction->account->updateBalance($reverseImpact);
                    }

                    $linkedTransaction->delete();
                }
            }

            $reverseImpact = -$transaction->balanceImpact();
            if ($reverseImpact !== 0.0) {
                $transaction->account->updateBalance($reverseImpact);
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

    public function bulkUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'exists:finance_transactions,id'],
            // Own or built-in categories only.
            'category_id' => ['nullable', Rule::exists('finance_categories', 'id')->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', auth()->id()))],
        ]);

        $userId = auth()->id();

        $transactions = Transaction::whereIn('id', $validated['ids'])
            ->whereHas('account', fn ($q) => $q->where('user_id', $userId))
            ->get();

        if ($transactions->isEmpty()) {
            return response()->json([
                'message' => 'No valid transactions found',
            ], 404);
        }

        $updateData = [];
        if (array_key_exists('category_id', $validated)) {
            $updateData['category_id'] = $validated['category_id'];
        }

        if (! empty($updateData)) {
            Transaction::whereIn('id', $transactions->pluck('id'))->update($updateData);
        }

        return response()->json([
            'message' => "{$transactions->count()} transaction(s) updated successfully",
            'data' => ['updated_count' => $transactions->count()],
        ]);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'exists:finance_transactions,id'],
        ]);

        $userId = auth()->id();

        $transactions = Transaction::whereIn('id', $validated['ids'])
            ->whereHas('account', fn ($q) => $q->where('user_id', $userId))
            ->get();

        if ($transactions->isEmpty()) {
            return response()->json([
                'message' => 'No valid transactions found',
            ], 404);
        }

        $deletedCount = 0;

        DB::transaction(function () use ($transactions, &$deletedCount) {
            $processedIds = [];

            foreach ($transactions as $transaction) {
                if (in_array($transaction->id, $processedIds, true)) {
                    continue;
                }

                if ($transaction->transfer_transaction_id) {
                    $linkedTransaction = Transaction::find($transaction->transfer_transaction_id);

                    if ($linkedTransaction) {
                        $reverseImpact = -$linkedTransaction->balanceImpact();
                        if ($reverseImpact !== 0.0) {
                            $linkedTransaction->account->updateBalance($reverseImpact);
                        }

                        $linkedTransaction->delete();
                        $processedIds[] = $linkedTransaction->id;
                    }
                }

                $reverseImpact = -$transaction->balanceImpact();
                if ($reverseImpact !== 0.0) {
                    $transaction->account->updateBalance($reverseImpact);
                }

                $transaction->delete();
                $processedIds[] = $transaction->id;
                $deletedCount++;
            }
        });

        return response()->json([
            'message' => "{$deletedCount} transaction(s) deleted successfully",
            'data' => ['deleted_count' => $deletedCount],
        ]);
    }

    public function conversionPreview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_account_id' => ['required', 'exists:finance_accounts,id'],
            'to_account_id' => ['required', 'exists:finance_accounts,id', 'different:from_account_id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $userId = auth()->id();

        // Verify both accounts belong to user
        Account::where('id', $validated['from_account_id'])
            ->where('user_id', $userId)
            ->firstOrFail();

        Account::where('id', $validated['to_account_id'])
            ->where('user_id', $userId)
            ->firstOrFail();

        $result = $this->transactionService->getTransferConversionPreview(
            $validated['from_account_id'],
            $validated['to_account_id'],
            (float) $validated['amount']
        );

        return response()->json([
            'data' => $result,
        ]);
    }
}
