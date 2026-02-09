<?php

namespace Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Inertia\{Inertia, Response};
use Modules\Finance\Contracts\TransactionParserInterface;
use Modules\Finance\Models\{Account, Category, SmartInputHistory, Transaction};
use Modules\Finance\Services\{
    ClaudeTransactionParser,
    DeepSeekTransactionParser,
    GeminiTransactionParser,
    TransactionParserFactory,
    TransactionService
};
use Spatie\Image\Enums\ImageDriver;
use Spatie\Image\Image;

class SmartInputController extends Controller
{
    protected TransactionParserInterface $parser;

    public function __construct(
        protected TransactionService $transactionService
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
     * Get parser for receipt/image input — prefers Gemini for speed,
     * falls back to current provider's vision capability
     */
    protected function getReceiptParser(): ?TransactionParserInterface
    {
        $provider = config('services.smart_input.provider', 'deepseek');

        // Gemini is fastest for image parsing, use it when available
        if ($provider !== 'gemini' && config('services.gemini.api_key')) {
            return TransactionParserFactory::make('gemini');
        }

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
            ->orderBy('name')
            ->get(['id', 'name', 'account_type', 'currency_code']);

        $categories = Category::where(function ($q) use ($userId) {
            $q->where('user_id', $userId)->orWhereNull('user_id');
        })
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'icon', 'color']);

        // Load recent chat history for continuity
        $recentHistory = SmartInputHistory::forUser($userId)
            ->with('media')
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
                'error' => "Image parsing is not supported by {$provider}. Please configure GEMINI_API_KEY or CLAUDE_API_KEY in your .env file to enable image parsing.",
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
                'error' => "Image parsing is not supported by {$provider}. Please configure GEMINI_API_KEY or CLAUDE_API_KEY in your .env file to enable image parsing.",
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
            'history_id' => ['nullable', 'integer', 'exists:finance_smart_input_histories,id'],
        ]);

        // Verify account belongs to user
        Account::where('id', $validated['account_id'])
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $data = [
            'account_id' => $validated['account_id'],
            'amount' => $validated['amount'],
            'description' => $validated['description'],
            'category_id' => $validated['category_id'] ?? null,
            'transaction_date' => $validated['transaction_date'],
            'notes' => $validated['notes'] ?? null,
        ];

        // Use TransactionService to properly update account balance
        $transaction = $validated['type'] === 'income'
            ? $this->transactionService->recordIncome($data)
            : $this->transactionService->recordExpense($data);

        // Link history record to saved transaction
        if (! empty($validated['history_id'])) {
            SmartInputHistory::where('id', $validated['history_id'])
                ->where('user_id', auth()->id())
                ->update([
                    'transaction_id' => $transaction->id,
                    'transaction_saved' => true,
                ]);
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

        // Fall back to first active account
        $firstAccount = Account::where('user_id', $userId)
            ->where('is_active', true)
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
            $webpPath = null;

            try {
                $webpPath = $this->convertToWebp($attachment);
            } catch (\Throwable $e) {
                report($e);
            }

            try {
                if ($webpPath && file_exists($webpPath) && filesize($webpPath) > 0) {
                    $originalName = pathinfo($attachment->getClientOriginalName(), PATHINFO_FILENAME) . '.webp';

                    $history->addMedia($webpPath)
                        ->usingFileName($originalName)
                        ->toMediaCollection('input_attachments');
                } else {
                    $history->addMedia($attachment)
                        ->preservingOriginal()
                        ->toMediaCollection('input_attachments');
                }
            } catch (\Throwable $e) {
                report($e);
            } finally {
                if ($webpPath && file_exists($webpPath)) {
                    @unlink($webpPath);
                }
            }
        }

        return $history;
    }

    /**
     * Convert an uploaded image to WebP format for smaller file size.
     * Returns the temp file path on success, null if conversion not needed/possible.
     */
    protected function convertToWebp(UploadedFile $file): ?string
    {
        $mime = $file->getMimeType();

        if (! str_starts_with($mime, 'image/')) {
            return null;
        }

        if ($mime === 'image/webp') {
            return null;
        }

        $tempPath = sys_get_temp_dir() . '/' . Str::uuid() . '.webp';

        Image::useImageDriver(ImageDriver::Gd)
            ->loadFile($file->getPathname())
            ->format('webp')
            ->quality(85)
            ->save($tempPath);

        return $tempPath;
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
