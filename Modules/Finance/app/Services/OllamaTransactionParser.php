<?php

namespace Modules\Finance\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Http, Log};
use Modules\Settings\Services\AiCredentialResolver;

class OllamaTransactionParser extends DeepSeekTransactionParser
{
    protected string $visionModel;

    protected string $audioModel;

    public function __construct()
    {
        $this->visionModel = config('services.ollama.vision_model', 'llama3.2-vision');
        $this->audioModel = config('services.ollama.audio_model', 'nemotron3');
    }

    public function parseVoice(UploadedFile $audioFile, string $language = 'vi'): array
    {
        $audioBase64 = base64_encode(file_get_contents($audioFile->getRealPath()));
        $mimeType = $audioFile->getMimeType() ?: 'audio/webm';

        Log::info('Ollama voice parsing request', [
            'mime' => $mimeType,
            'file_size' => $audioFile->getSize(),
            'model' => $this->audioModel,
        ]);

        $prompt = $this->getParsePrompt($language);

        $response = $this->callOllamaApi([
            'model' => $this->audioModel,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $prompt],
                        ['type' => 'image_url', 'image_url' => ['url' => "data:{$mimeType};base64,{$audioBase64}"]],
                    ],
                ],
            ],
        ]);

        if (isset($response['error'])) {
            Log::error('Ollama voice parsing failed', ['error' => $response['error']]);

            return [
                'success' => false,
                'error' => 'Voice input is temporarily unavailable. Please type your transaction instead (e.g., "Cafe 50k").',
                'confidence' => 0,
            ];
        }

        return $this->parseResponse($response);
    }

    public function parseReceipt(UploadedFile $imageFile, string $language = 'vi'): array
    {
        $imageBase64 = base64_encode(file_get_contents($imageFile->getRealPath()));
        $mimeType = $imageFile->getMimeType() ?: 'image/jpeg';

        $prompt = $this->getReceiptPrompt($language);

        $response = $this->callOllamaApi([
            'model' => $this->visionModel,
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

        // Use Ollama's native format parameter instead of response_format —
        // not all models support response_format via the OpenAI-compat endpoint.
        $response = $this->callOllamaApi([
            'model' => app(AiCredentialResolver::class)->ollamaModel(),
            'format' => 'json',
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        return $this->parseResponse($response);
    }

    public function parseTextWithImage(string $text, string $imageBase64, string $mimeType, string $language = 'vi'): array
    {
        $prompt = $this->getTextWithImagePrompt($language, $text);

        $response = $this->callOllamaApi([
            'model' => $this->visionModel,
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

    protected function callOllamaApi(array $payload): array
    {
        $baseUrl = app(AiCredentialResolver::class)->ollamaBaseUrl();
        $url = "{$baseUrl}/v1/chat/completions";

        $headers = ['Content-Type' => 'application/json'];

        $apiKey = app(AiCredentialResolver::class)->apiKey('ollama');
        if ($apiKey !== null) {
            $headers['Authorization'] = "Bearer {$apiKey}";
        }

        try {
            $response = Http::timeout(180)
                ->withOptions(['allow_redirects' => false])
                ->withHeaders($headers)
                ->post($url, $payload);

            if ($response->failed()) {
                Log::error('Ollama API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                $body = $response->json();
                $errorMessage = match (true) {
                    $response->status() === 404 => "Ollama model '".($payload['model'] ?? 'unknown')."' not found at {$baseUrl}. Check your Ollama settings.",
                    $response->status() === 401 => 'Ollama authentication failed. Check OLLAMA_API_KEY in your .env file.',
                    $response->status() >= 500 => 'Ollama service error: '.($body['error']['message'] ?? $response->body()),
                    default => 'Ollama error (HTTP '.$response->status().'): '.($body['error']['message'] ?? $response->body()),
                };

                return ['error' => $errorMessage, 'status' => $response->status()];
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Ollama API exception', ['message' => $e->getMessage()]);

            return ['error' => "Cannot connect to Ollama at {$baseUrl}. Check your Ollama Base URL and network connection."];
        }
    }
}
