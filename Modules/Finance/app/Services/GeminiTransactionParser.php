<?php

namespace Modules\Finance\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Http, Log};
use Modules\Finance\Contracts\TransactionParserInterface;
use Modules\Finance\Models\{Account, Category};

class GeminiTransactionParser implements TransactionParserInterface
{
    protected string $apiKey;

    protected string $aiStudioBaseUrl = 'https://generativelanguage.googleapis.com/v1beta';

    protected string $model;

    /** @var "ai_studio"|"vertex" */
    protected string $backend;

    protected ?string $vertexProject;

    protected string $vertexRegion;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
        $this->model = config('services.gemini.model', 'gemini-2.0-flash');
        $this->backend = config('services.gemini.backend', 'ai_studio');
        $this->vertexProject = config('services.gemini.vertex.project_id');
        $this->vertexRegion = config('services.gemini.vertex.region', 'asia-southeast1');

        // google/auth reads GOOGLE_APPLICATION_CREDENTIALS from the process env, not Laravel's env().
        // Resolve relative paths against base_path() and export it for ADC.
        if ($this->backend === 'vertex') {
            $credentialsPath = config('services.gemini.vertex.credentials_path');
            if ($credentialsPath && ! str_starts_with($credentialsPath, '/')) {
                $credentialsPath = base_path($credentialsPath);
            }
            if ($credentialsPath) {
                putenv("GOOGLE_APPLICATION_CREDENTIALS={$credentialsPath}");
            }
        }
    }

    /**
     * Parse voice audio to extract transaction details
     * Note: Browser typically records in WebM/Opus format which Gemini doesn't directly support.
     * We try multiple MIME types to find one that works.
     */
    public function parseVoice(UploadedFile $audioFile, string $language = 'vi'): array
    {
        $audioBase64 = base64_encode(file_get_contents($audioFile->getRealPath()));
        $originalMime = $audioFile->getMimeType() ?: 'audio/webm';

        Log::info('Voice parsing request', [
            'original_mime' => $originalMime,
            'file_size' => $audioFile->getSize(),
            'audio_length' => strlen($audioBase64),
        ]);

        // Gemini officially supports: audio/wav, audio/mp3, audio/aiff, audio/aac, audio/ogg, audio/flac
        // Browser WebM/Opus is not officially supported, but we'll try compatible formats
        $mimeTypesToTry = $this->getMimeTypesToTry($originalMime);

        $prompt = $this->getParsePrompt($language);
        $lastError = null;

        foreach ($mimeTypesToTry as $mimeType) {
            Log::info("Trying audio format: {$mimeType}");

            $response = $this->callGeminiApi([
                'contents' => [
                    [
                        // Vertex AI requires explicit role; AI Studio accepts it too.
                        'role' => 'user',
                        'parts' => [
                            ['text' => $prompt],
                            [
                                'inline_data' => [
                                    'mime_type' => $mimeType,
                                    'data' => $audioBase64,
                                ],
                            ],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.1,
                    'topP' => 0.8,
                    'maxOutputTokens' => 1024,
                ],
            ]);

            // If successful, return the result
            if (! isset($response['error'])) {
                Log::info("Audio format {$mimeType} succeeded");

                return $this->parseResponse($response);
            }

            $lastError = $response['error'];

            // If it's not a format error (400), don't try other formats
            if (($response['status'] ?? 0) !== 400) {
                break;
            }
        }

        // All formats failed - suggest using text input instead
        return [
            'success' => false,
            'error' => 'Voice input is temporarily unavailable. Please use the Text tab to enter your transaction (e.g., "Cafe 50k").',
            'confidence' => 0,
        ];
    }

    /**
     * Get list of MIME types to try based on original format
     */
    protected function getMimeTypesToTry(string $originalMime): array
    {
        // If it's already a supported format, just try that
        $supportedFormats = ['audio/wav', 'audio/mp3', 'audio/aiff', 'audio/aac', 'audio/ogg', 'audio/flac'];

        foreach ($supportedFormats as $format) {
            if (str_contains($originalMime, str_replace('audio/', '', $format))) {
                return [$format];
            }
        }

        // For WebM/Opus (browser default), try these formats
        // WebM contains Opus or Vorbis codec, which is similar to OGG
        if (str_contains($originalMime, 'webm') || str_contains($originalMime, 'opus')) {
            return ['audio/ogg', 'audio/webm', 'audio/mp3'];
        }

        // For MP4 audio
        if (str_contains($originalMime, 'mp4')) {
            return ['audio/aac', 'audio/mp4', 'audio/mp3'];
        }

        // Default fallback
        return [$originalMime, 'audio/ogg', 'audio/mp3'];
    }

    /**
     * Parse receipt/bill image to extract transaction details
     */
    public function parseReceipt(UploadedFile $imageFile, string $language = 'vi'): array
    {
        $imageBase64 = base64_encode(file_get_contents($imageFile->getRealPath()));
        $mimeType = $imageFile->getMimeType() ?: 'image/jpeg';

        $prompt = $this->getReceiptPrompt($language);

        $response = $this->callGeminiApi([
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $prompt],
                        [
                            'inline_data' => [
                                'mime_type' => $mimeType,
                                'data' => $imageBase64,
                            ],
                        ],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.1,
                'topP' => 0.8,
                'maxOutputTokens' => 1024,
            ],
        ]);

        return $this->parseResponse($response);
    }

    /**
     * Parse text input (for voice-to-text scenarios)
     */
    public function parseText(string $text, string $language = 'vi'): array
    {
        $prompt = $this->getParsePrompt($language)."\n\nInput: {$text}";

        $response = $this->callGeminiApi([
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.1,
                'topP' => 0.8,
                'maxOutputTokens' => 1024,
            ],
        ]);

        return $this->parseResponse($response);
    }

    public function parseTextWithImage(string $text, string $imageBase64, string $mimeType, string $language = 'vi'): array
    {
        $prompt = $this->getTextWithImagePrompt($language, $text);

        $response = $this->callGeminiApi([
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $prompt],
                        [
                            'inline_data' => [
                                'mime_type' => $mimeType,
                                'data' => $imageBase64,
                            ],
                        ],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.1,
                'topP' => 0.8,
                'maxOutputTokens' => 1024,
            ],
        ]);

        return $this->parseResponse($response);
    }

    /**
     * Match category hint to user's categories
     */
    public function matchCategory(string $hint, int $userId, string $type = 'expense'): ?array
    {
        $categories = Category::where(function ($q) use ($userId) {
            $q->where('user_id', $userId)->orWhereNull('user_id');
        })
            ->where('is_active', true)
            ->where(function ($q) use ($type) {
                $q->where('type', $type)->orWhere('type', 'both');
            })
            ->get();

        $hintLower = mb_strtolower($hint);

        // Direct name match
        foreach ($categories as $category) {
            if (mb_strtolower($category->name) === $hintLower) {
                return ['id' => $category->id, 'name' => $category->name];
            }
        }

        // Partial match
        foreach ($categories as $category) {
            if (str_contains(mb_strtolower($category->name), $hintLower) ||
                str_contains($hintLower, mb_strtolower($category->name))) {
                return ['id' => $category->id, 'name' => $category->name];
            }
        }

        // Common Vietnamese mappings
        $mappings = [
            'ăn' => ['food', 'ăn uống', 'thực phẩm'],
            'cafe' => ['food', 'ăn uống', 'coffee'],
            'xăng' => ['transport', 'di chuyển', 'transportation'],
            'điện' => ['utilities', 'tiện ích', 'điện nước'],
            'nước' => ['utilities', 'tiện ích', 'điện nước'],
            'lương' => ['salary', 'thu nhập', 'income'],
            'thuê' => ['rent', 'nhà ở', 'housing'],
            'mua sắm' => ['shopping', 'mua sắm'],
            'giải trí' => ['entertainment', 'giải trí'],
        ];

        foreach ($mappings as $keyword => $relatedTerms) {
            if (str_contains($hintLower, $keyword)) {
                foreach ($categories as $category) {
                    $catLower = mb_strtolower($category->name);
                    foreach ($relatedTerms as $term) {
                        if (str_contains($catLower, $term)) {
                            return ['id' => $category->id, 'name' => $category->name];
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * Match account hint to user's accounts
     */
    public function matchAccount(string $hint, int $userId): ?array
    {
        $accounts = Account::where('user_id', $userId)
            ->where('is_active', true)
            ->get();

        $hintLower = mb_strtolower($hint);

        // Direct name match
        foreach ($accounts as $account) {
            if (mb_strtolower($account->name) === $hintLower) {
                return ['id' => $account->id, 'name' => $account->name];
            }
        }

        // Type-based match
        $typeMap = [
            'tiền mặt' => 'cash',
            'cash' => 'cash',
            'thẻ' => 'credit_card',
            'card' => 'credit_card',
            'ngân hàng' => 'bank',
            'bank' => 'bank',
        ];

        foreach ($typeMap as $keyword => $type) {
            if (str_contains($hintLower, $keyword)) {
                $match = $accounts->firstWhere('account_type', $type);
                if ($match) {
                    return ['id' => $match->id, 'name' => $match->name];
                }
            }
        }

        // Return first active account as fallback
        $first = $accounts->first();

        return $first ? ['id' => $first->id, 'name' => $first->name] : null;
    }

    protected function callGeminiApi(array $payload): array
    {
        // Vertex uses the official SDK which sends canonical Google client headers
        // (x-goog-api-client, gccl/gax/gapic identifiers) so calls authenticate
        // properly against the paid Vertex quota.
        if ($this->backend === 'vertex') {
            if (! $this->vertexProject) {
                return [
                    'error' => 'GOOGLE_CLOUD_PROJECT not set; required for Vertex AI.',
                    'status' => 500,
                ];
            }

            $vertexClient = new VertexGeminiClient($this->vertexProject, $this->vertexRegion, $this->model);

            return $vertexClient->generateContent($payload);
        }

        $result = $this->executeRequest('ai_studio', $this->buildAiStudioUrl(), $this->buildAiStudioHeaders(), $payload);
        unset($result['_abuse_filter']);

        return $result;
    }

    /**
     * Execute a single Gemini API request. Returns the decoded JSON on success,
     * or ['error' => message, 'status' => code, '_abuse_filter' => bool] on failure.
     * The internal `_abuse_filter` flag lets the caller decide whether to fall back.
     */
    protected function executeRequest(string $backend, string $url, array $headers, array $payload): array
    {
        try {
            $response = Http::timeout(60)
                ->withHeaders($headers)
                // Retry transient failures (5xx, 429, 417 abuse filter, network errors)
                // with exponential backoff. throw:false keeps the response object on failure
                // so we can inspect status and surface a useful error.
                ->retry(2, 1000, function (\Throwable $exception, $request) {
                    if ($exception instanceof \Illuminate\Http\Client\ConnectionException) {
                        return true;
                    }

                    if ($exception instanceof \Illuminate\Http\Client\RequestException) {
                        $status = $exception->response->status();

                        return in_array($status, [417, 429, 500, 502, 503, 504], true);
                    }

                    return false;
                }, throw: false)
                ->post($url, $payload);

            if ($response->failed()) {
                $body = $response->body();
                $errorBody = $response->json('error') ?? [];

                // Detect Google's edge anti-abuse "Sorry..." HTML interstitial.
                // It returns status 417 with HTML body, NOT a Gemini API error.
                $isAbuseFilter = $response->status() === 417
                    && str_contains($body, '<html')
                    && str_contains($body, 'automated queries');

                Log::error('Gemini API error', [
                    'backend' => $backend,
                    'model' => $this->model,
                    'url' => $url,
                    'status' => $response->status(),
                    'error_code' => $errorBody['code'] ?? null,
                    'error_message' => $errorBody['message'] ?? null,
                    'error_status' => $errorBody['status'] ?? null,
                    'is_abuse_filter' => $isAbuseFilter,
                    // Truncate HTML interstitial to first 800 chars so we can identify what Google
                    // is actually serving (real abuse page vs region-unavailable vs billing alert).
                    'full_body' => $isAbuseFilter ? mb_substr($body, 0, 800) : $body,
                ]);

                $errorMessage = match (true) {
                    $isAbuseFilter => 'Google temporarily blocked this request from our server. Please retry in a moment, or use a different AI provider.',
                    $response->status() === 429 => 'AI service quota exceeded. Please try again later or check your API plan.',
                    in_array($response->status(), [401, 403], true) => 'AI service authentication failed. Please check your API key.',
                    $response->status() === 404 => "AI model '{$this->model}' not available on {$backend}. Check GEMINI_MODEL or GOOGLE_CLOUD_REGION.",
                    $response->status() === 400 => 'Invalid request. '.($errorBody['message'] ?? 'Please try again.'),
                    $response->status() >= 500 => 'AI service temporarily unavailable. Please try again.',
                    default => 'AI service error ('.$response->status().'). Please try again.',
                };

                return [
                    'error' => $errorMessage,
                    'status' => $response->status(),
                    '_abuse_filter' => $isAbuseFilter,
                ];
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Gemini API exception', ['backend' => $backend, 'message' => $e->getMessage()]);

            return ['error' => 'Could not reach AI service: '.$e->getMessage(), '_abuse_filter' => false];
        }
    }

    protected function buildAiStudioUrl(): string
    {
        return "{$this->aiStudioBaseUrl}/models/{$this->model}:generateContent";
    }

    protected function buildAiStudioHeaders(): array
    {
        return [
            'x-goog-api-key' => $this->apiKey,
            'Content-Type' => 'application/json',
            // Identifying User-Agent prevents Google's edge anti-abuse filter
            // from returning the 417 "Sorry..." HTML page on datacenter IPs.
            'User-Agent' => 'mokey-finance/1.0 (+laravel-http-client)',
        ];
    }

    protected function parseResponse(array $response): array
    {
        if (isset($response['error'])) {
            return [
                'success' => false,
                'error' => $response['error'],
                'confidence' => 0,
            ];
        }

        $text = $response['candidates'][0]['content']['parts'][0]['text'] ?? '';

        // Extract JSON from response
        $json = $this->extractJson($text);

        if (! $json) {
            return [
                'success' => false,
                'error' => 'Could not parse response',
                'raw_text' => $text,
                'confidence' => 0,
            ];
        }

        return [
            'success' => true,
            'type' => $json['type'] ?? 'expense',
            'amount' => $this->normalizeAmount($json['amount'] ?? 0),
            'description' => $json['description'] ?? '',
            'category_hint' => $json['category_hint'] ?? null,
            'account_hint' => $json['account_hint'] ?? null,
            'transfer_account_hint' => $json['transfer_account_hint'] ?? null,
            'transaction_date' => $this->normalizeDate($json['date_hint'] ?? null),
            'confidence' => $json['confidence'] ?? 0.8,
            'raw_text' => $json['raw_text'] ?? $text,
        ];
    }

    protected function extractJson(string $text): ?array
    {
        // Try to find JSON in the response
        if (preg_match('/\{[\s\S]*\}/', $text, $matches)) {
            $json = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $json;
            }
        }

        return null;
    }

    protected function normalizeAmount($amount): int
    {
        if (is_numeric($amount)) {
            return (int) $amount;
        }

        // Handle Vietnamese number shortcuts
        $amount = str_replace([',', '.', ' '], '', (string) $amount);
        $amount = mb_strtolower($amount);

        if (preg_match('/(\d+)\s*(k|nghìn|nghin)/i', $amount, $m)) {
            return (int) $m[1] * 1000;
        }

        if (preg_match('/(\d+)\s*(tr|triệu|trieu)/i', $amount, $m)) {
            return (int) $m[1] * 1000000;
        }

        if (preg_match('/(\d+)\s*(tỷ|ty)/i', $amount, $m)) {
            return (int) $m[1] * 1000000000;
        }

        return (int) preg_replace('/\D/', '', $amount);
    }

    protected function normalizeDate(?string $dateHint): string
    {
        if (! $dateHint) {
            return now()->format('Y-m-d');
        }

        $hint = mb_strtolower($dateHint);

        // Vietnamese date hints
        if (str_contains($hint, 'hôm nay') || str_contains($hint, 'today')) {
            return now()->format('Y-m-d');
        }

        if (str_contains($hint, 'hôm qua') || str_contains($hint, 'yesterday')) {
            return now()->subDay()->format('Y-m-d');
        }

        if (str_contains($hint, 'tuần trước') || str_contains($hint, 'last week')) {
            return now()->subWeek()->format('Y-m-d');
        }

        if (str_contains($hint, 'tháng trước') || str_contains($hint, 'last month')) {
            return now()->subMonth()->format('Y-m-d');
        }

        // Try to parse as date
        try {
            return \Carbon\Carbon::parse($dateHint)->format('Y-m-d');
        } catch (\Exception $e) {
            return now()->format('Y-m-d');
        }
    }

    protected function getParsePrompt(string $language): string
    {
        $langInstructions = $language === 'vi'
            ? 'Input is in Vietnamese. Handle Vietnamese number shortcuts: k/nghìn=×1000, tr/triệu=×1000000.'
            : 'Input is in English.';

        return <<<PROMPT
You are a financial transaction parser. Extract transaction details from voice/text input.

{$langInstructions}

Extract:
- type: "income", "expense", or "transfer" (default: expense). Use "transfer" when money moves between accounts (chuyển khoản, chuyển tiền, transfer, gửi tiền, nạp tiền vào tài khoản)
- amount: number in VND (convert shortcuts to full numbers)
- description: brief description of transaction
- category_hint: suggested category (food, transport, utilities, salary, shopping, entertainment, etc.) — omit for transfers
- account_hint: source payment method if mentioned (cash, card, bank)
- transfer_account_hint: destination account/bank if mentioned for transfers (e.g. "Vietcombank", "MoMo", "tiết kiệm", "savings")
- date_hint: relative date if mentioned (hôm nay, hôm qua, today, yesterday)
- confidence: 0.0 to 1.0 based on clarity of input

Vietnamese examples:
- "Ăn sáng 35 nghìn" → expense, 35000, breakfast, food
- "Lương tháng 15 triệu" → income, 15000000, monthly salary, salary
- "Đổ xăng 200k hôm qua" → expense, 200000, gas, transport, yesterday
- "Cafe 50k" → expense, 50000, coffee, food
- "Chuyển 5tr sang Vietcombank" → transfer, 5000000, transfer to Vietcombank, transfer_account_hint: "Vietcombank"
- "Chuyển khoản 2tr MoMo" → transfer, 2000000, transfer to MoMo, transfer_account_hint: "MoMo"

Return ONLY valid JSON, no explanation:
{"type":"expense","amount":50000,"description":"Coffee","category_hint":"food","date_hint":"today","confidence":0.95}
PROMPT;
    }

    protected function getReceiptPrompt(string $language): string
    {
        $langInstructions = $language === 'vi'
            ? 'Receipt is likely in Vietnamese. Look for: Tổng cộng, Thành tiền, Total for amount. Ngày for date.'
            : 'Receipt is in English. Look for Total, Amount, Date fields.';

        return <<<PROMPT
You are a receipt/bill OCR parser. Extract transaction details from the image.

{$langInstructions}

Extract:
- type: "expense" (receipts are typically expenses)
- amount: total amount in the local currency
- description: store/vendor name or main item
- category_hint: category based on vendor type (restaurant=food, gas station=transport, etc.)
- date_hint: transaction date if visible
- confidence: 0.0 to 1.0 based on image clarity and extraction certainty
- raw_text: key text extracted from receipt

Return ONLY valid JSON, no explanation:
{"type":"expense","amount":150000,"description":"Highland Coffee","category_hint":"food","date_hint":"2026-01-04","confidence":0.92,"raw_text":"Highland Coffee - Total: 150,000 VND"}
PROMPT;
    }

    protected function getTextWithImagePrompt(string $language, string $userText): string
    {
        $langInstructions = $language === 'vi'
            ? 'Input is in Vietnamese. Handle Vietnamese number shortcuts: k/nghìn=×1000, tr/triệu=×1000000. Receipt may be in Vietnamese.'
            : 'Input is in English.';

        return <<<PROMPT
You are a financial transaction parser. The user provides text context AND an image (receipt/bill). Use both to extract transaction details.

User says: "{$userText}"

{$langInstructions}

Extract:
- type: "income" or "expense" (default: expense)
- amount: number in local currency (convert shortcuts to full numbers)
- description: brief description combining text and image context
- category_hint: suggested category (food, transport, utilities, salary, shopping, entertainment, etc.)
- account_hint: payment method if mentioned (cash, card, bank)
- date_hint: date if visible or mentioned
- confidence: 0.0 to 1.0 based on clarity of input
- raw_text: key text extracted from the image

Return ONLY valid JSON, no explanation:
{"type":"expense","amount":150000,"description":"Highland Coffee","category_hint":"food","date_hint":"2026-01-04","confidence":0.92,"raw_text":"Highland Coffee - Total: 150,000 VND"}
PROMPT;
    }
}
