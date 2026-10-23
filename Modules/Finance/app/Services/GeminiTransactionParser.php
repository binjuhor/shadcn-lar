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

class GeminiTransactionParser implements TransactionParserInterface
{
    use ParsesTransactionEntities;

    protected string $aiStudioBaseUrl = 'https://generativelanguage.googleapis.com/v1beta';

    protected string $model;

    /** @var "ai_studio"|"vertex" */
    protected string $backend;

    protected ?string $vertexProject;

    protected string $vertexRegion;

    public function __construct()
    {
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
            'error' => 'Voice input is temporarily unavailable. Please type your transaction instead (e.g., "Cafe 50k").',
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
                    if ($exception instanceof ConnectionException) {
                        return true;
                    }

                    if ($exception instanceof RequestException) {
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
            'x-goog-api-key' => app(AiCredentialResolver::class)->apiKey('gemini'),
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
            return Carbon::parse($dateHint)->format('Y-m-d');
        } catch (\Exception $e) {
            return now()->format('Y-m-d');
        }
    }
}
