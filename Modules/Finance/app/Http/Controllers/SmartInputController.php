<?php

namespace Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\{Inertia, Response};
use Modules\Finance\Contracts\TransactionParserInterface;
use Modules\Finance\Models\{Account, Category, SmartInputHistory, Transaction};
use Modules\Finance\Services\{
    BillAttachmentService,
    ClaudeTransactionParser,
    DeepSeekTransactionParser,
    GeminiTransactionParser,
    TransactionParserFactory,
    TransactionService
};

class SmartInputController extends Controller
{
    protected TransactionParserInterface $parser;

    public function __construct(
        protected TransactionService $transactionService,
        protected BillAttachmentService $billAttachmentService,
    ) {
        $this->parser = TransactionParserFactory::make();
    }

    /**
     * Check if the configured AI provider has an API key set
     */
    protected function isAiConfigured(): bool
    {
        $provider = config('services.smart_input.provider', 'deepseek');

        return (bool) match ($provider) {
            'gemini' => config('services.gemini.api_key'),
            'claude' => config('services.claude.api_key'),
            'deepseek' => config('services.deepseek.api_key'),
            'ollama' => config('services.ollama.base_url'),
            'openrouter' => config('services.openrouter.api_key'),
            'openai' => config('services.openai.api_key'),
            default => false,
        };
    }

    /**
     * Providers that don't support audio natively
     */
    protected const NO_VOICE_PROVIDERS = ['deepseek', 'ollama', 'openrouter', 'openai'];

    /**
     * Providers that don't support image/vision natively
     */
    protected const NO_VISION_PROVIDERS = ['deepseek'];

    /**
     * Get parser for voice input — falls back to Gemini/Claude since
     * DeepSeek, Ollama, OpenRouter, OpenAI don't support audio natively
     */
    protected function getVoiceParser(): ?TransactionParserInterface
    {
        $provider = config('services.smart_input.provider', 'deepseek');

        if (in_array($provider, self::NO_VOICE_PROVIDERS)) {
            if (config('services.gemini.api_key')) {
                return TransactionParserFactory::make('gemini');
            }

            if (config('services.claude.api_key')) {
                return TransactionParserFactory::make('claude');
            }

            return null;
        }

        return $this->parser;
    }

    /**
     * Get parser for receipt/image input — uses current provider's vision capability,
     * falls back to Claude for providers that don't support vision (e.g. DeepSeek)
     */
    protected function getReceiptParser(): ?TransactionParserInterface
    {
        $provider = config('services.smart_input.provider', 'deepseek');

        if (in_array($provider, self::NO_VISION_PROVIDERS) && config('services.claude.api_key')) {
            return TransactionParserFactory::make('claude');
        }

        if (in_array($provider, self::NO_VISION_PROVIDERS)) {
            return null;
        }

        return $this->parser;
    }

    /**
     * Show smart input page
     */
    public function index(): Response
    {
        $userId = auth()->id();

        $accounts = Account::where('user_id', $userId)
            ->where('is_active', true)
            ->orderByRaw("CASE WHEN currency_code = 'VND' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get(['id', 'name', 'account_type', 'currency_code']);

        $categories = Category::where(function ($q) use ($userId) {
            $q->where('user_id', $userId)->orWhereNull('user_id');
        })
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'icon', 'color']);

        // Load recent chat history for continuity. Eager-load the saved transaction so
        // the chat card can show the currency that was actually used (parsed_result alone
        // has no currency — that's only set when the user picks an account on save).
        $recentHistory = SmartInputHistory::forUser($userId)
            ->with(['media', 'transaction:id,currency_code'])
            ->latest()
            ->take(20)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (SmartInputHistory $h) => [
                'id' => $h->id,
                'input_type' => $h->input_type,
                'raw_text' => $h->raw_text,
                'parsed_result' => $h->parsed_result,
                'confidence' => $h->confidence,
                'transaction_saved' => $h->transaction_saved,
                'created_at' => $h->created_at->toISOString(),
                'media_url' => $h->getFirstMediaUrl('input_attachments') ?: null,
                'currency_code' => $h->transaction?->currency_code,
            ]);

        return Inertia::render('Finance::smart-input/index', [
            'accounts' => $accounts,
            'categories' => $categories,
            'recentHistory' => $recentHistory,
            'aiConfigured' => $this->isAiConfigured(),
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

        $language = $request->input('language', 'vi');
        $voiceParser = $this->getVoiceParser();

        if (! $voiceParser) {
            $provider = config('services.smart_input.provider', 'deepseek');

            return response()->json([
                'success' => false,
                'error' => "Voice input is not supported by {$provider}. Please configure GEMINI_API_KEY or CLAUDE_API_KEY in your .env file to enable voice parsing.",
            ], 422);
        }

        $result = $voiceParser->parseVoice(
            $request->file('audio'),
            $language
        );

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'error' => $result['error'] ?? 'Failed to parse audio',
            ], 422);
        }

        $history = $this->recordHistory(
            'voice',
            $result['raw_text'] ?? null,
            $result,
            $this->getProviderName($voiceParser),
            $language,
            $request->file('audio')
        );

        return $this->enrichResult($result, $voiceParser, $history->id);
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

        $language = $request->input('language', 'vi');
        $receiptParser = $this->getReceiptParser();

        if (! $receiptParser) {
            $provider = config('services.smart_input.provider', 'deepseek');

            return response()->json([
                'success' => false,
                'error' => "Image parsing is not supported by {$provider}. Switch to a provider with vision support (ollama, gemini, claude, openai) in your .env file.",
            ], 422);
        }

        $result = $receiptParser->parseReceipt(
            $request->file('image'),
            $language
        );

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'error' => $result['error'] ?? 'Failed to parse receipt',
            ], 422);
        }

        $history = $this->recordHistory(
            'image',
            null,
            $result,
            $this->getProviderName($receiptParser),
            $language,
            $request->file('image')
        );

        return $this->enrichResult($result, $receiptParser, $history->id);
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

        $text = trim($request->input('text'));
        $language = $request->input('language', 'vi');

        // Handle multiple numeric values separated by spaces/commas (e.g. "50k 30k 100k")
        if ($this->isMultipleNumericInput($text)) {
            return $this->handleMultipleNumericInput($text, $language);
        }

        // Handle simple numeric inputs (just a number like "210.55" or "50000")
        if ($this->isSimpleNumericInput($text)) {
            return $this->handleSimpleNumericInput($text, $language);
        }

        $result = $this->parser->parseText($text, $language);

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'error' => $result['error'] ?? 'Failed to parse text',
            ], 422);
        }

        $history = $this->recordHistory(
            'text',
            $text,
            $result,
            $this->getProviderName($this->parser),
            $language
        );

        return $this->enrichResult($result, null, $history->id);
    }

    /**
     * Parse text + image input: image is parsed by AI, text becomes transaction note
     */
    public function parseTextWithImage(Request $request)
    {
        $request->validate([
            'text' => ['required', 'string', 'max:1000'],
            'image' => ['required', 'file', 'image', 'max:10240'],
            'language' => ['nullable', 'string', 'in:vi,en'],
        ]);

        $notes = trim($request->input('text'));
        $language = $request->input('language', 'vi');
        $imageFile = $request->file('image');

        $receiptParser = $this->getReceiptParser();

        if (! $receiptParser) {
            $provider = config('services.smart_input.provider', 'deepseek');

            return response()->json([
                'success' => false,
                'error' => "Image parsing is not supported by {$provider}. Switch to a provider with vision support (ollama, gemini, claude, openai) in your .env file.",
            ], 422);
        }

        $result = $receiptParser->parseReceipt($imageFile, $language);

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'error' => $result['error'] ?? 'Failed to parse receipt',
            ], 422);
        }

        $history = $this->recordHistory(
            'text_image',
            $notes,
            $result,
            $this->getProviderName($receiptParser),
            $language,
            $imageFile
        );

        return $this->enrichResult($result, $receiptParser, $history->id, $notes);
    }

    /**
     * Check if input is just a simple number
     */
    protected function isSimpleNumericInput(string $text): bool
    {
        // Match: 210.55, 50000, 50,000, 50.000, 1.5, etc.
        $normalized = str_replace([',', ' '], '', $text);

        return preg_match('/^\d+(\.\d+)?$/', $normalized) === 1;
    }

    /**
     * Handle simple numeric input by creating a basic expense transaction
     */
    protected function handleSimpleNumericInput(string $text, string $language = 'vi')
    {
        $userId = auth()->id();

        // Parse the number (handle both comma and dot as thousand/decimal separators)
        $normalized = str_replace([',', ' '], '', $text);
        $amount = (float) $normalized;

        $suggestedAccount = $this->getDefaultSmartInputAccount($userId);

        $parsedResult = [
            'type' => 'expense',
            'amount' => $amount,
            'description' => '',
            'confidence' => 0.5,
            'raw_text' => $text,
        ];

        $history = $this->recordHistory('text', $text, $parsedResult, null, $language);

        return response()->json([
            'success' => true,
            'data' => [
                'type' => 'expense',
                'amount' => $amount,
                'description' => '',
                'suggested_category' => null,
                'suggested_account' => $suggestedAccount,
                'transaction_date' => now()->format('Y-m-d'),
                'confidence' => 0.5,
                'raw_text' => $text,
            ],
            'history_id' => $history->id,
        ]);
    }

    /**
     * Check if input contains multiple numeric values separated by spaces, commas, or newlines.
     * Each value can use Vietnamese shortcuts (k, tr, tỷ).
     * Must contain at least 2 values.
     */
    protected function isMultipleNumericInput(string $text): bool
    {
        $tokens = preg_split('/[\s,;]+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);

        if (count($tokens) < 2) {
            return false;
        }

        foreach ($tokens as $token) {
            if (! $this->isSingleNumericToken($token)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a single token is a numeric value (plain number or with k/tr/tỷ suffix)
     */
    protected function isSingleNumericToken(string $token): bool
    {
        $normalized = str_replace([',', '.'], '', mb_strtolower(trim($token)));

        // Plain number: 50000, 210
        if (preg_match('/^\d+$/', $normalized)) {
            return true;
        }

        // With Vietnamese shortcut: 50k, 5tr, 1tỷ
        return preg_match('/^\d+(k|nghìn|nghin|tr|triệu|trieu|tỷ|ty)$/u', $normalized) === 1;
    }

    /**
     * Parse a single numeric token into its amount value
     */
    protected function parseNumericToken(string $token): float
    {
        $normalized = str_replace([',', ' '], '', mb_strtolower(trim($token)));

        if (preg_match('/^(\d+)\s*(k|nghìn|nghin)$/u', $normalized, $m)) {
            return (float) $m[1] * 1000;
        }

        if (preg_match('/^(\d+)\s*(tr|triệu|trieu)$/u', $normalized, $m)) {
            return (float) $m[1] * 1000000;
        }

        if (preg_match('/^(\d+)\s*(tỷ|ty)$/u', $normalized, $m)) {
            return (float) $m[1] * 1000000000;
        }

        // Remove dots used as thousand separators (Vietnamese style: 50.000)
        $normalized = str_replace('.', '', $normalized);

        return (float) $normalized;
    }

    /**
     * Handle multiple numeric values — returns an array of transactions
     */
    protected function handleMultipleNumericInput(string $text, string $language = 'vi')
    {
        $userId = auth()->id();
        $tokens = preg_split('/[\s,;]+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);
        $suggestedAccount = $this->getDefaultSmartInputAccount($userId);

        $items = [];

        foreach ($tokens as $token) {
            $amount = $this->parseNumericToken($token);

            $items[] = [
                'type' => 'expense',
                'amount' => $amount,
                'description' => '',
                'suggested_category' => null,
                'suggested_account' => $suggestedAccount,
                'transaction_date' => now()->format('Y-m-d'),
                'confidence' => 0.5,
                'raw_text' => $token,
            ];
        }

        $history = $this->recordHistory('text', $text, [
            'type' => 'expense',
            'amount' => array_sum(array_column($items, 'amount')),
            'description' => 'Multiple transactions',
            'confidence' => 0.5,
            'raw_text' => $text,
        ], null, $language);

        return response()->json([
            'success' => true,
            'multiple' => true,
            'items' => $items,
            'history_id' => $history->id,
        ]);
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
            'transfer_account_id' => ['nullable', 'exists:finance_accounts,id'],
            'transaction_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'history_id' => ['nullable', 'integer', 'exists:finance_smart_input_histories,id'],
        ]);

        // Verify account belongs to user
        Account::where('id', $validated['account_id'])
            ->where('user_id', auth()->id())
            ->firstOrFail();

        // For transfers, verify destination account belongs to user and differs from source
        if ($validated['type'] === 'transfer') {
            if (empty($validated['transfer_account_id'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'Destination account is required for transfers.',
                ], 422);
            }

            if ($validated['transfer_account_id'] == $validated['account_id']) {
                return response()->json([
                    'success' => false,
                    'error' => 'Source and destination accounts must be different.',
                ], 422);
            }

            Account::where('id', $validated['transfer_account_id'])
                ->where('user_id', auth()->id())
                ->firstOrFail();
        }

        if ($validated['type'] === 'transfer') {
            $result = $this->transactionService->recordTransfer([
                'from_account_id' => $validated['account_id'],
                'to_account_id' => $validated['transfer_account_id'],
                'amount' => $validated['amount'],
                'description' => $validated['description'],
                'transaction_date' => $validated['transaction_date'],
            ]);

            $transaction = $result['debit'];
        } else {
            $data = [
                'account_id' => $validated['account_id'],
                'amount' => $validated['amount'],
                'description' => $validated['description'],
                'category_id' => $validated['category_id'] ?? null,
                'transaction_date' => $validated['transaction_date'],
                'notes' => $validated['notes'] ?? null,
            ];

            $transaction = $validated['type'] === 'income'
                ? $this->transactionService->recordIncome($data)
                : $this->transactionService->recordExpense($data);
        }

        // Link history record to saved transaction and persist user corrections
        if (! empty($validated['history_id'])) {
            $history = SmartInputHistory::where('id', $validated['history_id'])
                ->where('user_id', auth()->id())
                ->first();

            if ($history) {
                $updatedResult = array_merge($history->parsed_result ?? [], [
                    'type' => $validated['type'],
                    'amount' => $validated['amount'],
                    'description' => $validated['description'],
                    'transaction_date' => $validated['transaction_date'],
                ]);

                $history->update([
                    'transaction_id' => $transaction->id,
                    'transaction_saved' => true,
                    'parsed_result' => $updatedResult,
                ]);

                // Copy any receipt/bill attached during smart-input onto the transaction
                // so users can find the bill from the transaction list later.
                $this->billAttachmentService->copyCollection($history, 'input_attachments', $transaction, 'bills');
            }
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Transaction created successfully.',
            ]);
        }

        return redirect()
            ->route('dashboard.finance.smart-input')
            ->with('success', 'Transaction created successfully.');
    }

    protected function enrichResult(array $result, ?TransactionParserInterface $parser = null, ?int $historyId = null, ?string $notes = null)
    {
        $parser = $parser ?? $this->parser;
        $userId = auth()->id();

        $suggestedCategory = null;
        if (! empty($result['category_hint'])) {
            $suggestedCategory = $parser->matchCategory(
                $result['category_hint'],
                $userId,
                $result['type'] ?? 'expense'
            );
        }

        $suggestedAccount = null;
        if (! empty($result['account_hint'])) {
            $suggestedAccount = $parser->matchAccount($result['account_hint'], $userId);
        }

        if (! $suggestedAccount) {
            $suggestedAccount = $this->getDefaultSmartInputAccount($userId);
        }

        // Match transfer destination account hint
        $suggestedTransferAccount = null;
        if (! empty($result['transfer_account_hint']) && ($result['type'] ?? 'expense') === 'transfer') {
            $suggestedTransferAccount = $parser->matchAccount($result['transfer_account_hint'], $userId);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'type' => $result['type'] ?? 'expense',
                'amount' => $result['amount'] ?? 0,
                'description' => $result['description'] ?? '',
                'suggested_category' => $suggestedCategory,
                'suggested_account' => $suggestedAccount,
                'suggested_transfer_account' => $suggestedTransferAccount,
                'transaction_date' => $result['transaction_date'] ?? now()->format('Y-m-d'),
                'confidence' => $result['confidence'] ?? 0.8,
                'raw_text' => $result['raw_text'] ?? null,
                'notes' => $notes,
            ],
            'history_id' => $historyId,
        ]);
    }

    /**
     * Get default account for smart input based on user settings
     */
    protected function getDefaultSmartInputAccount(int $userId): ?array
    {
        $user = auth()->user();
        $financeSettings = $user->finance_settings ?? [];
        $defaultAccountId = $financeSettings['default_smart_input_account_id'] ?? null;

        // Try user's configured default smart input account first
        if ($defaultAccountId) {
            $account = Account::where('id', $defaultAccountId)
                ->where('user_id', $userId)
                ->where('is_active', true)
                ->first();

            if ($account) {
                return ['id' => $account->id, 'name' => $account->name];
            }
        }

        // Fall back to default payment account
        $defaultPayment = Account::getDefaultPayment($userId);
        if ($defaultPayment) {
            return ['id' => $defaultPayment->id, 'name' => $defaultPayment->name];
        }

        // Fall back to first active VND account, then any active account
        $firstAccount = Account::where('user_id', $userId)
            ->where('is_active', true)
            ->orderByRaw("CASE WHEN currency_code = 'VND' THEN 0 ELSE 1 END")
            ->first();

        if ($firstAccount) {
            return ['id' => $firstAccount->id, 'name' => $firstAccount->name];
        }

        return null;
    }

    protected function recordHistory(
        string $inputType,
        ?string $rawText,
        array $parsedResult,
        ?string $aiProvider,
        string $language,
        ?UploadedFile $attachment = null
    ): SmartInputHistory {
        $history = SmartInputHistory::create([
            'user_id' => auth()->id(),
            'input_type' => $inputType,
            'raw_text' => $rawText,
            'parsed_result' => $parsedResult,
            'ai_provider' => $aiProvider,
            'language' => $language,
            'confidence' => $parsedResult['confidence'] ?? null,
        ]);

        if ($attachment) {
            $this->billAttachmentService->attach($history, 'input_attachments', $attachment);
        }

        return $history;
    }

    protected function getProviderName(TransactionParserInterface $parser): string
    {
        return match (true) {
            $parser instanceof ClaudeTransactionParser => 'claude',
            $parser instanceof GeminiTransactionParser => 'gemini',
            $parser instanceof DeepSeekTransactionParser => 'deepseek',
            default => 'unknown',
        };
    }
}
