<?php

namespace Modules\Finance\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Http, Log};

class OpenRouterTransactionParser extends DeepSeekTransactionParser
{
    protected string $visionModel;

    public function __construct()
    {
        $this->apiKey = config('services.openrouter.api_key');
        $this->model = config('services.openrouter.model', 'openrouter/auto');
        $this->visionModel = config('services.openrouter.vision_model', 'openrouter/auto');
    }

    public function parseReceipt(UploadedFile $imageFile, string $language = 'vi'): array
    {
        $imageBase64 = base64_encode(file_get_contents($imageFile->getRealPath()));
        $mimeType = $imageFile->getMimeType() ?: 'image/jpeg';

        $prompt = $this->getReceiptPrompt($language);

        $response = $this->callOpenRouterApi([
            'model' => $this->visionModel,
            'max_tokens' => 1024,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $prompt],
                        ['type' => 'image_url', 'image_url' => ['url' => "data:{$mimeType};base64,{$imageBase64}"]],
                    ],
                ],
            ],
        ]);

        return $this->parseResponse($response);
    }

    public function parseText(string $text, string $language = 'vi'): array
    {
        $prompt = $this->getParsePrompt($language)."\n\nInput: {$text}";

        $response = $this->callOpenRouterApi([
            'model' => $this->model,
            'max_tokens' => 1024,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        return $this->parseResponse($response);
    }

    public function parseTextWithImage(string $text, string $imageBase64, string $mimeType, string $language = 'vi'): array
    {
        $prompt = $this->getTextWithImagePrompt($language, $text);

        $response = $this->callOpenRouterApi([
            'model' => $this->visionModel,
            'max_tokens' => 1024,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $prompt],
                        ['type' => 'image_url', 'image_url' => ['url' => "data:{$mimeType};base64,{$imageBase64}"]],
                    ],
                ],
            ],
        ]);

        return $this->parseResponse($response);
    }

    protected function callOpenRouterApi(array $payload): array
    {
        try {
            $response = Http::timeout(60)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                    'HTTP-Referer' => config('app.url', 'http://localhost'),
                    'X-Title' => config('app.name', 'Mokey'),
                ])
                ->post('https://openrouter.ai/api/v1/chat/completions', $payload);

            if ($response->failed()) {
                Log::error('OpenRouter API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                $body = $response->json();
                $apiErrorMessage = $body['error']['message'] ?? null;

                $errorMessage = match (true) {
                    $response->status() === 429 => 'AI service rate limited. Please try again later.',
                    $response->status() === 401 || $response->status() === 403 => 'OpenRouter authentication failed. Please check your API key.',
                    $response->status() === 402 => 'OpenRouter credits exhausted. Please add credits.',
                    $response->status() >= 500 => 'OpenRouter service temporarily unavailable. Please try again.',
                    default => $apiErrorMessage ?? 'OpenRouter service error. Please try again.',
                };

                return ['error' => $errorMessage, 'status' => $response->status()];
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('OpenRouter API exception', ['message' => $e->getMessage()]);

            return ['error' => $e->getMessage()];
        }
    }
}
