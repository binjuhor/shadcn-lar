<?php

namespace Modules\Finance\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Http, Log};

class OpenAiTransactionParser extends DeepSeekTransactionParser
{
    protected string $visionModel;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
        $this->model = config('services.openai.model', 'gpt-4o-mini');
        $this->visionModel = config('services.openai.vision_model', 'gpt-4o');
    }

    public function parseReceipt(UploadedFile $imageFile, string $language = 'vi'): array
    {
        $imageBase64 = base64_encode(file_get_contents($imageFile->getRealPath()));
        $mimeType = $imageFile->getMimeType() ?: 'image/jpeg';

        $prompt = $this->getReceiptPrompt($language);

        $response = $this->callOpenAiApi([
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

        $response = $this->callOpenAiApi([
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

        $response = $this->callOpenAiApi([
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

    protected function callOpenAiApi(array $payload): array
    {
        try {
            $response = Http::timeout(60)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ])
                ->post('https://api.openai.com/v1/chat/completions', $payload);

            if ($response->failed()) {
                Log::error('OpenAI API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                $body = $response->json();
                $apiErrorMessage = $body['error']['message'] ?? null;

                $errorMessage = match (true) {
                    $response->status() === 429 => 'OpenAI rate limited. Please try again later.',
                    $response->status() === 401 => 'OpenAI authentication failed. Please check your API key.',
                    $response->status() === 402 || str_contains($apiErrorMessage ?? '', 'insufficient_quota') => 'OpenAI credits exhausted. Please add credits.',
                    $response->status() >= 500 => 'OpenAI service temporarily unavailable. Please try again.',
                    default => $apiErrorMessage ?? 'OpenAI service error. Please try again.',
                };

                return ['error' => $errorMessage, 'status' => $response->status()];
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('OpenAI API exception', ['message' => $e->getMessage()]);

            return ['error' => $e->getMessage()];
        }
    }
}
