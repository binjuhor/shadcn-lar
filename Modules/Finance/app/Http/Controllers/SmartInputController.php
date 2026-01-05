<?php

namespace Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Finance\Contracts\TransactionParserInterface;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\Category;
use Modules\Finance\Models\Transaction;
use Modules\Finance\Services\TransactionParserFactory;

class SmartInputController extends Controller
{
    protected TransactionParserInterface $parser;

    public function __construct()
    {
        $this->parser = TransactionParserFactory::make();
    }

    /**
     * Show smart input page
     */
    public function index(): Response
    {
        $accounts = Account::where('user_id', auth()->id())
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'account_type', 'currency_code']);

        $categories = Category::where(function ($q) {
            $q->where('user_id', auth()->id())->orWhereNull('user_id');
        })
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'icon', 'color']);

        return Inertia::render('Finance::smart-input/index', [
            'accounts' => $accounts,
            'categories' => $categories,
        ]);
    }

    /**
     * Parse voice input (web route for CSRF)
     */
    public function parseVoice(Request $request)
    {
        $request->validate([
            'audio' => ['required', 'file', 'max:10240'],
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
     * Parse receipt image (web route for CSRF)
     */
    public function parseReceipt(Request $request)
    {
        $request->validate([
            'image' => ['required', 'file', 'image', 'max:10240'],
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
     * Parse text input (web route for CSRF)
     */
    public function parseText(Request $request)
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
     * Store transaction from smart input
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', 'in:income,expense,transfer'],
            'amount' => ['required', 'numeric', 'min:1'],
            'description' => ['required', 'string', 'max:255'],
            'account_id' => ['required', 'exists:finance_accounts,id'],
            'category_id' => ['nullable', 'exists:finance_categories,id'],
            'transaction_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $account = Account::where('id', $validated['account_id'])
            ->where('user_id', auth()->id())
            ->firstOrFail();

        Transaction::create([
            'user_id' => auth()->id(),
            'account_id' => $validated['account_id'],
            'transaction_type' => $validated['type'],
            'amount' => $validated['amount'],
            'description' => $validated['description'],
            'category_id' => $validated['category_id'] ?? null,
            'transaction_date' => $validated['transaction_date'],
            'notes' => $validated['notes'] ?? null,
            'currency_code' => $account->currency_code,
            'is_reconciled' => false,
        ]);

        return redirect()
            ->route('dashboard.finance.smart-input')
            ->with('success', 'Transaction created successfully.');
    }

    protected function enrichResult(array $result)
    {
        $userId = auth()->id();

        $suggestedCategory = null;
        if (! empty($result['category_hint'])) {
            $suggestedCategory = $this->parser->matchCategory(
                $result['category_hint'],
                $userId,
                $result['type'] ?? 'expense'
            );
        }

        $suggestedAccount = null;
        if (! empty($result['account_hint'])) {
            $suggestedAccount = $this->parser->matchAccount($result['account_hint'], $userId);
        }

        if (! $suggestedAccount) {
            // Try default payment account first
            $defaultAccount = Account::getDefaultPayment($userId);
            if ($defaultAccount) {
                $suggestedAccount = ['id' => $defaultAccount->id, 'name' => $defaultAccount->name];
            } else {
                // Fall back to first active account
                $firstAccount = Account::where('user_id', $userId)
                    ->where('is_active', true)
                    ->first();
                if ($firstAccount) {
                    $suggestedAccount = ['id' => $firstAccount->id, 'name' => $firstAccount->name];
                }
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
