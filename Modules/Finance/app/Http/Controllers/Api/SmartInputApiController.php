<?php

namespace Modules\Finance\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Finance\Exceptions\SmartInputException;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\Category;
use Modules\Finance\Models\SmartInputHistory;
use Modules\Finance\Models\Transaction;
use Modules\Finance\Services\SmartInputParsingService;
use Modules\Finance\Services\SmartInputTransactionService;
use Modules\Finance\Services\TransactionService;

/**
 * @deprecated Use the AI Assistant API (`/api/v1/finance/assistant/*`), which handles text,
 *             voice, and receipt input in one chat. Kept only for older mobile app versions.
 */
class SmartInputApiController extends Controller
{
    public function __construct(
        private TransactionService $transactionService,
        private SmartInputParsingService $parsing,
        private SmartInputTransactionService $transactions,
    ) {}

    /**
     * Parse voice audio to extract transaction details
     */
    public function parseVoice(Request $request): JsonResponse
    {
        $request->validate([
            'audio' => ['required', 'file', 'max:10240', 'mimes:webm,ogg,mp3,wav,aac,flac,aiff,mp4,m4a'],
            'language' => ['nullable', 'string', 'in:vi,en'],
        ]);

        return $this->respond(fn () => $this->parsing->parseVoice(
            $request->user(),
            $request->file('audio'),
            $request->input('language', 'vi'),
        ));
    }

    /**
     * Parse receipt/bill image to extract transaction details
     */
    public function parseReceipt(Request $request): JsonResponse
    {
        $request->validate([
            // 'image' rule rejects HEIC (image/heic or application/octet-stream on servers without libheif)
            'image' => ['required', 'file', 'extensions:jpg,jpeg,png,gif,bmp,webp,heic,heif', 'max:10240'],
            'language' => ['nullable', 'string', 'in:vi,en'],
        ]);

        return $this->respond(fn () => $this->parsing->parseReceipt(
            $request->user(),
            $request->file('image'),
            $request->input('language', 'vi'),
        ));
    }

    /**
     * Parse text input (for voice transcription fallback)
     */
    public function parseText(Request $request): JsonResponse
    {
        $request->validate([
            'text' => ['required', 'string', 'max:1000'],
            'language' => ['nullable', 'string', 'in:vi,en'],
        ]);

        return $this->respond(fn () => $this->parsing->parseText(
            $request->user(),
            $request->input('text'),
            $request->input('language', 'vi'),
        ));
    }

    public function parseTextWithImage(Request $request): JsonResponse
    {
        $request->validate([
            'text' => ['required', 'string', 'max:1000'],
            'image' => ['required', 'file', 'extensions:jpg,jpeg,png,gif,bmp,webp,heic,heif', 'max:10240'],
            'language' => ['nullable', 'string', 'in:vi,en'],
        ]);

        return $this->respond(fn () => $this->parsing->parseReceipt(
            $request->user(),
            $request->file('image'),
            $request->input('language', 'vi'),
            $request->input('text'),
        ));
    }

    /**
     * Create transaction from parsed data
     */
    public function storeTransaction(Request $request): JsonResponse
    {
        $validated = $request->validate(SmartInputTransactionService::rules());

        try {
            $transaction = $this->transactions->save($request->user(), $validated);
        } catch (SmartInputException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $transaction->id,
                'message' => 'Transaction created successfully',
                'history_id' => $validated['history_id'] ?? null,
            ],
        ], 201);
    }

    public function history(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'input_type' => ['nullable', 'string', 'in:text,voice,image,text_image'],
            'saved' => ['nullable', 'string', 'in:yes,no'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'before_id' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'between:1,50'],
        ]);

        $limit = (int) ($validated['limit'] ?? 20);
        $query = SmartInputHistory::forUser(auth()->id())
            ->with(['media', 'transaction:id,description,amount,transaction_type,currency_code'])
            ->latest();

        if (! empty($validated['input_type'])) {
            $query->where('input_type', $validated['input_type']);
        }

        if (! empty($validated['saved'])) {
            $query->where('transaction_saved', $validated['saved'] === 'yes');
        }

        if (! empty($validated['date_from'])) {
            $query->whereDate('created_at', '>=', $validated['date_from']);
        }

        if (! empty($validated['date_to'])) {
            $query->whereDate('created_at', '<=', $validated['date_to']);
        }

        if (! empty($validated['before_id'])) {
            $query->where('id', '<', $validated['before_id']);
        }

        $items = $query->take($limit + 1)->get();
        $hasMore = $items->count() > $limit;
        $items = $items->take($limit)->values();

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $items->map(fn (SmartInputHistory $history) => $this->historyData($history)),
                'has_more' => $hasMore,
                'next_before_id' => $hasMore ? $items->last()?->id : null,
            ],
        ]);
    }

    public function destroyHistory(int $history): JsonResponse
    {
        $historyRecord = SmartInputHistory::forUser(auth()->id())->findOrFail($history);
        $historyRecord->delete();

        return response()->json([
            'success' => true,
            'message' => 'History entry deleted.',
        ]);
    }

    /**
     * List user accounts for dropdown
     */
    public function accounts(): JsonResponse
    {
        $accounts = Account::where('user_id', auth()->id())
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'account_type', 'currency_code', 'current_balance']);

        return response()->json([
            'success' => true,
            'data' => $accounts,
        ]);
    }

    /**
     * List user categories for dropdown
     */
    public function categories(Request $request): JsonResponse
    {
        $type = $request->query('type'); // income, expense, or null for all

        $query = Category::where(function ($q) {
            $q->where('user_id', auth()->id())->orWhereNull('user_id');
        })->where('is_active', true);

        if ($type) {
            $query->where(function ($q) use ($type) {
                $q->where('type', $type)->orWhere('type', 'both');
            });
        }

        $categories = $query->orderBy('name')->get(['id', 'name', 'type', 'icon', 'color']);

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    /**
     * Sync offline transactions from mobile app
     */
    public function syncOffline(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'transactions' => ['required', 'array', 'min:1', 'max:100'],
            'transactions.*.type' => ['required', 'in:income,expense,transfer'],
            'transactions.*.amount' => ['required', 'numeric', 'min:0.01'],
            'transactions.*.description' => ['required', 'string', 'max:255'],
            'transactions.*.account_id' => ['required', 'exists:finance_accounts,id'],
            'transactions.*.category_id' => ['nullable', 'exists:finance_categories,id'],
            'transactions.*.transaction_date' => ['required', 'date'],
            'transactions.*.notes' => ['nullable', 'string'],
            'transactions.*.offline_id' => ['nullable', 'string', 'max:100'],
            'transactions.*.transfer_account_id' => ['nullable', 'exists:finance_accounts,id'],
        ]);

        $userId = auth()->id();
        $results = [];

        foreach ($validated['transactions'] as $index => $txData) {
            $account = Account::where('id', $txData['account_id'])
                ->where('user_id', $userId)
                ->first();

            if (! $account) {
                $results[] = [
                    'index' => $index,
                    'offline_id' => $txData['offline_id'] ?? null,
                    'success' => false,
                    'error' => 'Account not found or not owned by user',
                ];

                continue;
            }

            try {
                if ($txData['type'] === 'transfer') {
                    $destination = Account::where('id', $txData['transfer_account_id'] ?? null)
                        ->where('user_id', $userId)
                        ->whereKeyNot($account->id)
                        ->firstOrFail();

                    $transaction = $this->transactionService->recordTransfer([
                        'from_account_id' => $account->id,
                        'to_account_id' => $destination->id,
                        'amount' => $txData['amount'],
                        'description' => $txData['description'],
                        'transaction_date' => $txData['transaction_date'],
                        'notes' => $txData['notes'] ?? null,
                    ])['debit'];
                } else {
                    $transaction = Transaction::create([
                        'account_id' => $txData['account_id'],
                        'transaction_type' => $txData['type'],
                        'amount' => $txData['amount'],
                        'description' => $txData['description'],
                        'category_id' => $txData['category_id'] ?? null,
                        'transaction_date' => $txData['transaction_date'],
                        'notes' => $txData['notes'] ?? null,
                        'currency_code' => $account->currency_code,
                        'is_reconciled' => false,
                    ]);
                }

                $results[] = [
                    'index' => $index,
                    'offline_id' => $txData['offline_id'] ?? null,
                    'success' => true,
                    'transaction_id' => $transaction->id,
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'index' => $index,
                    'offline_id' => $txData['offline_id'] ?? null,
                    'success' => false,
                    'error' => 'Failed to create transaction',
                ];
            }
        }

        $successCount = collect($results)->where('success', true)->count();
        $failCount = collect($results)->where('success', false)->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total' => count($results),
                'synced' => $successCount,
                'failed' => $failCount,
                'results' => $results,
            ],
        ]);
    }

    /**
     * Quick entry for common transactions
     */
    public function quickEntry(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:income,expense'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['required', 'string', 'max:255'],
            'account_id' => ['nullable', 'exists:finance_accounts,id'],
        ]);

        $userId = auth()->id();

        $account = null;
        if (! empty($validated['account_id'])) {
            $account = Account::where('id', $validated['account_id'])
                ->where('user_id', $userId)
                ->first();
        }

        if (! $account) {
            $account = Account::where('user_id', $userId)
                ->where('is_active', true)
                ->first();
        }

        if (! $account) {
            return response()->json([
                'success' => false,
                'error' => 'No active account found',
            ], 422);
        }

        $suggestedCategory = $this->parsing->parser($request->user())->matchCategory(
            $validated['description'],
            $userId,
            $validated['type']
        );

        $transaction = Transaction::create([
            'user_id' => $userId,
            'account_id' => $account->id,
            'transaction_type' => $validated['type'],
            'amount' => $validated['amount'],
            'description' => $validated['description'],
            'category_id' => $suggestedCategory['id'] ?? null,
            'transaction_date' => now(),
            'currency_code' => $account->currency_code,
            'is_reconciled' => false,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $transaction->id,
                'type' => $transaction->transaction_type,
                'amount' => $transaction->amount,
                'description' => $transaction->description,
                'category' => $suggestedCategory,
                'account' => [
                    'id' => $account->id,
                    'name' => $account->name,
                ],
                'transaction_date' => $transaction->transaction_date->format('Y-m-d'),
            ],
        ], 201);
    }

    protected function historyData(SmartInputHistory $history): array
    {
        return [
            'id' => $history->id,
            'input_type' => $history->input_type,
            'raw_text' => $history->raw_text,
            'parsed_result' => $history->parsed_result,
            'confidence' => $history->confidence,
            'transaction_saved' => $history->transaction_saved,
            'created_at' => $history->created_at->toISOString(),
            'media_url' => $history->getFirstMediaUrl('input_attachments') ?: null,
            'currency_code' => $history->transaction?->currency_code,
        ];
    }

    /**
     * @param  callable(): array{drafts: array, history_id: int, multiple: bool}  $parse
     */
    protected function respond(callable $parse): JsonResponse
    {
        try {
            return response()->json($this->parsing->payload($parse()));
        } catch (SmartInputException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }
}
