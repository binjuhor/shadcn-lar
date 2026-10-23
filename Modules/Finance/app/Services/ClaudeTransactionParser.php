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

class ClaudeTransactionParser implements TransactionParserInterface
{
    use ParsesTransactionEntities;

    protected string $baseUrl = 'https://api.anthropic.com/v1';

    protected string $model;

    public function __construct()
    {
        $this->model = config('services.claude.model', 'claude-sonnet-4-20250514');
    }

    public function parseVoice(UploadedFile $audioFile, string $language = 'vi'): array
    {
        // Claude's API has no audio input; voice is routed to Gemini instead
        return [
            'success' => false,
            'error' => 'Claude does not support voice input. Please type your transaction instead.',
            'confidence' => 0,
        ];
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

    public function parseTextWithImage(string $text, string $imageBase64, string $mimeType, string $language = 'vi'): array
    {
        $prompt = $this->getTextWithImagePrompt($language, $text);

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

    protected function callClaudeApi(array $payload): array
    {
        $url = "{$this->baseUrl}/messages";

        try {
            $response = Http::timeout(60)
                ->withHeaders([
                    'x-api-key' => app(AiCredentialResolver::class)->apiKey('claude'),
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                    'User-Agent' => 'mokey-finance/1.0 (+laravel-http-client)',
                ])
                // Retry transient failures: Claude returns 529 "overloaded_error" at peak hours,
                // 429 rate limits, and 5xx network blips. Exponential-ish backoff.
                ->retry(2, 1000, function (\Throwable $exception, $request) {
                    if ($exception instanceof ConnectionException) {
                        return true;
                    }

                    if ($exception instanceof RequestException) {
                        $status = $exception->response->status();

                        return in_array($status, [429, 500, 502, 503, 504, 529], true);
                    }

                    return false;
                }, throw: false)
                ->post($url, $payload);

            if ($response->failed()) {
                Log::error('Claude API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                $body = $response->json();
                $apiErrorMessage = $body['error']['message'] ?? null;
                $apiErrorType = $body['error']['type'] ?? null;

                $errorMessage = match (true) {
                    str_contains($apiErrorMessage ?? '', 'credit balance') => 'Claude API requires credits. Please add credits at console.anthropic.com or switch to Gemini.',
                    $apiErrorType === 'overloaded_error' || $response->status() === 529 => 'Claude is overloaded right now. Please try again in a moment.',
                    $response->status() === 429 => 'Claude rate limited. Please try again in a moment.',
                    in_array($response->status(), [401, 403], true) => 'Claude authentication failed. Please check your API key.',
                    $response->status() >= 500 => 'Claude temporarily unavailable. Please try again.',
                    default => ($apiErrorMessage ?? 'Claude error').' (HTTP '.$response->status().')',
                };

                return ['error' => $errorMessage, 'status' => $response->status()];
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Claude API exception', ['message' => $e->getMessage()]);

            return ['error' => 'Could not reach Claude: '.$e->getMessage()];
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
