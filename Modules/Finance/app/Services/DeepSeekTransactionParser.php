<?php

namespace Modules\Finance\Services;

use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Http, Log};
use Modules\Finance\Contracts\TransactionParserInterface;
use Modules\Finance\Traits\ParsesTransactionEntities;
use Modules\Settings\Services\AiCredentialResolver;

class DeepSeekTransactionParser implements TransactionParserInterface
{
    use ParsesTransactionEntities;

    protected string $baseUrl = 'https://api.deepseek.com/v1';

    protected string $model;

    public function __construct()
    {
        $this->model = config('services.deepseek.model', 'deepseek-chat');
    }

    public function parseVoice(UploadedFile $audioFile, string $language = 'vi'): array
    {
        // DeepSeek doesn't support audio directly, transcribe first or use text
        return [
            'success' => false,
            'error' => 'DeepSeek does not support voice input. Please use text input instead.',
            'confidence' => 0,
        ];
    }

    public function parseReceipt(UploadedFile $imageFile, string $language = 'vi'): array
    {
        $imageBase64 = base64_encode(file_get_contents($imageFile->getRealPath()));
        $mimeType = $imageFile->getMimeType() ?: 'image/jpeg';

        $prompt = $this->getReceiptPrompt($language);

        $response = $this->callDeepSeekApi([
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
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => "data:{$mimeType};base64,{$imageBase64}",
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

        $response = $this->callDeepSeekApi([
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

    public function parseTextWithImage(string $text, string $imageBase64, string $mimeType, string $language = 'vi'): array
    {
        $prompt = $this->getTextWithImagePrompt($language, $text);

        $response = $this->callDeepSeekApi([
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
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => "data:{$mimeType};base64,{$imageBase64}",
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        return $this->parseResponse($response);
    }

    protected function callDeepSeekApi(array $payload): array
    {
        $url = "{$this->baseUrl}/chat/completions";

        try {
            $response = Http::timeout(60)
                ->withHeaders([
                    'Authorization' => 'Bearer '.app(AiCredentialResolver::class)->apiKey('deepseek'),
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'mokey-finance/1.0 (+laravel-http-client)',
                ])
                // Retry transient failures with exponential-ish backoff.
                ->retry(2, 1000, function (\Throwable $exception, $request) {
                    if ($exception instanceof ConnectionException) {
                        return true;
                    }

                    if ($exception instanceof RequestException) {
                        $status = $exception->response->status();

                        return in_array($status, [429, 500, 502, 503, 504], true);
                    }

                    return false;
                }, throw: false)
                ->post($url, $payload);

            if ($response->failed()) {
                Log::error('DeepSeek API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                $body = $response->json();
                $apiErrorMessage = $body['error']['message'] ?? null;

                $errorMessage = match (true) {
                    str_contains($apiErrorMessage ?? '', 'insufficient') => 'DeepSeek API balance insufficient. Please add credits.',
                    $response->status() === 429 => 'DeepSeek rate limited. Please try again in a moment.',
                    in_array($response->status(), [401, 403], true) => 'DeepSeek authentication failed. Please check your API key.',
                    $response->status() >= 500 => 'DeepSeek temporarily unavailable. Please try again.',
                    default => ($apiErrorMessage ?? 'DeepSeek error').' (HTTP '.$response->status().')',
                };

                return ['error' => $errorMessage, 'status' => $response->status()];
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('DeepSeek API exception', ['message' => $e->getMessage()]);

            return ['error' => 'Could not reach DeepSeek: '.$e->getMessage()];
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

        $text = $response['choices'][0]['message']['content'] ?? '';

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
        if (preg_match('/\{[\s\S]*\}/', $text, $matches)) {
            $json = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $json;
            }
        }

        return null;
    }

    protected function normalizeAmount($amount): float
    {
        return $this->normalizeTransactionAmount($amount);
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
            return Carbon::parse($dateHint)->format('Y-m-d');
        } catch (\Exception $e) {
            return now()->format('Y-m-d');
        }
    }
}
