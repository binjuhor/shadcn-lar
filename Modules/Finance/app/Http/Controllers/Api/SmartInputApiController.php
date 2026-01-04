<?php

namespace Modules\Finance\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\Category;
use Modules\Finance\Models\Transaction;
use Modules\Finance\Services\GeminiTransactionParser;

class SmartInputApiController extends Controller
{
    public function __construct(
        protected GeminiTransactionParser $parser
    ) {}

    /**
     * Parse voice audio to extract transaction details
     */
    public function parseVoice(Request $request): JsonResponse
    {
        $request->validate([
            'audio' => ['required', 'file', 'max:10240'], // 10MB max
            'language' => ['nullable', 'string', 'in:vi,en'],
        ]);

        $result = $this->parser->parseVoice(
            $request->file('audio'),
            $request->input('language', 'vi')
        );

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'error' => $result['error'] ?? 'Failed to parse audio',
            ], 422);
        }

        return $this->enrichResult($result);
    }

    /**
     * Parse receipt/bill image to extract transaction details
     */
    public function parseReceipt(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'file', 'image', 'max:10240'], // 10MB max
            'language' => ['nullable', 'string', 'in:vi,en'],
        ]);

        $result = $this->parser->parseReceipt(
            $request->file('image'),
            $request->input('language', 'vi')
        );

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'error' => $result['error'] ?? 'Failed to parse receipt',
            ], 422);
        }

        return $this->enrichResult($result);
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

        $result = $this->parser->parseText(
            $request->input('text'),
            $request->input('language', 'vi')
        );

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'error' => $result['error'] ?? 'Failed to parse text',
            ], 422);
        }

        return $this->enrichResult($result);
    }

    /**
     * Create transaction from parsed data
     */
    public function storeTransaction(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:income,expense,transfer'],
            'amount' => ['required', 'numeric', 'min:1'],
            'description' => ['required', 'string', 'max:255'],
            'account_id' => ['required', 'exists:finance_accounts,id'],
            'category_id' => ['nullable', 'exists:finance_categories,id'],
            'transaction_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'transfer_account_id' => ['nullable', 'exists:finance_accounts,id'],
        ]);

        // Verify account belongs to user
        $account = Account::where('id', $validated['account_id'])
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $transaction = Transaction::create([
            'account_id' => $validated['account_id'],
            'transaction_type' => $validated['type'],
            'amount' => $validated['amount'],
            'description' => $validated['description'],
            'category_id' => $validated['category_id'],
            'transaction_date' => $validated['transaction_date'],
            'notes' => $validated['notes'] ?? null,
            'currency_code' => $account->currency_code,
            'is_reconciled' => false,
            'transfer_account_id' => $validated['transfer_account_id'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $transaction->id,
                'message' => 'Transaction created successfully',
            ],
        ], 201);
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
     * Enrich parsed result with matched category/account
     */
    protected function enrichResult(array $result): JsonResponse
    {
        $userId = auth()->id();

        // Match category if hint provided
        $suggestedCategory = null;
        if (! empty($result['category_hint'])) {
            $suggestedCategory = $this->parser->matchCategory(
                $result['category_hint'],
                $userId,
                $result['type'] ?? 'expense'
            );
        }

        // Match account if hint provided
        $suggestedAccount = null;
        if (! empty($result['account_hint'])) {
            $suggestedAccount = $this->parser->matchAccount($result['account_hint'], $userId);
        }

        // Fallback to first active account
        if (! $suggestedAccount) {
            $firstAccount = Account::where('user_id', $userId)
                ->where('is_active', true)
                ->first();
            if ($firstAccount) {
                $suggestedAccount = ['id' => $firstAccount->id, 'name' => $firstAccount->name];
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'type' => $result['type'] ?? 'expense',
                'amount' => $result['amount'] ?? 0,
                'description' => $result['description'] ?? '',
                'suggested_category' => $suggestedCategory,
                'suggested_account' => $suggestedAccount,
                'transaction_date' => $result['transaction_date'] ?? now()->format('Y-m-d'),
                'confidence' => $result['confidence'] ?? 0.8,
                'raw_text' => $result['raw_text'] ?? null,
            ],
        ]);
    }
}
