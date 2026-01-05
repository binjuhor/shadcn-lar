<?php

namespace Modules\Finance\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Finance\Contracts\TransactionParserInterface;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\Category;

class ClaudeTransactionParser implements TransactionParserInterface
{
    protected string $apiKey;

    protected string $baseUrl = 'https://api.anthropic.com/v1';

    protected string $model;

    public function __construct()
    {
        $this->apiKey = config('services.claude.api_key');
        $this->model = config('services.claude.model', 'claude-sonnet-4-20250514');
    }

    public function parseVoice(UploadedFile $audioFile, string $language = 'vi'): array
    {
        $audioBase64 = base64_encode(file_get_contents($audioFile->getRealPath()));
        $mimeType = $audioFile->getMimeType() ?: 'audio/webm';

        $prompt = $this->getParsePrompt($language);

        $response = $this->callClaudeApi([
            'model' => $this->model,
            'max_tokens' => 1024,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => $prompt,
                        ],
                        [
                            'type' => 'document',
                            'source' => [
                                'type' => 'base64',
                                'media_type' => $mimeType,
                                'data' => $audioBase64,
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        return $this->parseResponse($response);
    }

    public function parseReceipt(UploadedFile $imageFile, string $language = 'vi'): array
    {
        $imageBase64 = base64_encode(file_get_contents($imageFile->getRealPath()));
        $mimeType = $imageFile->getMimeType() ?: 'image/jpeg';

        $prompt = $this->getReceiptPrompt($language);

        $response = $this->callClaudeApi([
            'model' => $this->model,
            'max_tokens' => 1024,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => $prompt,
                        ],
                        [
                            'type' => 'image',
                            'source' => [
                                'type' => 'base64',
                                'media_type' => $mimeType,
                                'data' => $imageBase64,
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        return $this->parseResponse($response);
    }

    public function parseText(string $text, string $language = 'vi'): array
    {
        $prompt = $this->getParsePrompt($language)."\n\nInput: {$text}";

        $response = $this->callClaudeApi([
            'model' => $this->model,
            'max_tokens' => 1024,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
        ]);

        return $this->parseResponse($response);
    }

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

        foreach ($categories as $category) {
            if (mb_strtolower($category->name) === $hintLower) {
                return ['id' => $category->id, 'name' => $category->name];
            }
        }

        foreach ($categories as $category) {
            if (str_contains(mb_strtolower($category->name), $hintLower) ||
                str_contains($hintLower, mb_strtolower($category->name))) {
                return ['id' => $category->id, 'name' => $category->name];
            }
        }

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

    public function matchAccount(string $hint, int $userId): ?array
    {
        $accounts = Account::where('user_id', $userId)
            ->where('is_active', true)
            ->get();

        $hintLower = mb_strtolower($hint);

        foreach ($accounts as $account) {
            if (mb_strtolower($account->name) === $hintLower) {
                return ['id' => $account->id, 'name' => $account->name];
            }
        }

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

        $first = $accounts->first();

        return $first ? ['id' => $first->id, 'name' => $first->name] : null;
    }

    protected function callClaudeApi(array $payload): array
    {
        $url = "{$this->baseUrl}/messages";

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'x-api-key' => $this->apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ])
                ->post($url, $payload);

            if ($response->failed()) {
                Log::error('Claude API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                $body = $response->json();
                $apiErrorMessage = $body['error']['message'] ?? null;

                $errorMessage = match (true) {
                    str_contains($apiErrorMessage ?? '', 'credit balance') => 'Claude API requires credits. Please add credits at console.anthropic.com or switch to Gemini.',
                    $response->status() === 429 => 'AI service rate limited. Please try again later.',
                    $response->status() === 401 || $response->status() === 403 => 'AI service authentication failed. Please check your API key.',
                    $response->status() >= 500 => 'AI service temporarily unavailable. Please try again.',
                    default => $apiErrorMessage ?? 'AI service error. Please try again.',
                };

                return ['error' => $errorMessage, 'status' => $response->status()];
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Claude API exception', ['message' => $e->getMessage()]);

            return ['error' => $e->getMessage()];
        }
    }

    protected function parseResponse(array $response): array
    {
        if (isset($response['error'])) {
            return [
                'success' => false,
                'error' => is_string($response['error']) ? $response['error'] : ($response['error']['message'] ?? 'Unknown error'),
                'confidence' => 0,
            ];
        }

        $text = $response['content'][0]['text'] ?? '';

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
            'transaction_date' => $this->normalizeDate($json['date_hint'] ?? null),
            'confidence' => $json['confidence'] ?? 0.8,
            'raw_text' => $json['raw_text'] ?? $text,
        ];
    }

    protected function extractJson(string $text): ?array
    {
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
- type: "income" or "expense" (default: expense)
- amount: number in VND (convert shortcuts to full numbers)
- description: brief description of transaction
- category_hint: suggested category (food, transport, utilities, salary, shopping, entertainment, etc.)
- account_hint: payment method if mentioned (cash, card, bank)
- date_hint: relative date if mentioned (hôm nay, hôm qua, today, yesterday)
- confidence: 0.0 to 1.0 based on clarity of input

Vietnamese examples:
- "Ăn sáng 35 nghìn" → expense, 35000, breakfast, food
- "Lương tháng 15 triệu" → income, 15000000, monthly salary, salary
- "Đổ xăng 200k hôm qua" → expense, 200000, gas, transport, yesterday
- "Cafe 50k" → expense, 50000, coffee, food

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
}
