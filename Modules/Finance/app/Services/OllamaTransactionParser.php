<?php

namespace Modules\Finance\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Http, Log};

class OllamaTransactionParser extends DeepSeekTransactionParser
{
    protected string $baseUrl;

    protected string $visionModel;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.ollama.base_url', 'http://localhost:11434'), '/');
        $this->model = config('services.ollama.model', 'llama3.2');
        $this->visionModel = config('services.ollama.vision_model', 'llama3.2-vision');
        $this->apiKey = config('services.ollama.api_key', '');
    }

    public function parseVoice(UploadedFile $audioFile, string $language = 'vi'): array
    {
        return [
            'success' => false,
            'error' => 'Ollama does not support voice input. Please use text input instead.',
            'confidence' => 0,
        ];
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

        $response = $this->callOllamaApi([
            'model' => $this->model,
            'response_format' => ['type' => 'json_object'],
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
        $url = "{$this->baseUrl}/v1/chat/completions";

        $headers = ['Content-Type' => 'application/json'];

        if ($this->apiKey !== '') {
            $headers['Authorization'] = "Bearer {$this->apiKey}";
        }

        try {
            $response = Http::timeout(180)
                ->withOptions(['allow_redirects' => ['strict' => true]])
                ->withHeaders($headers)
                ->post($url, $payload);

            if ($response->failed()) {
                Log::error('Ollama API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                $errorMessage = match (true) {
                    $response->status() >= 500 => 'Ollama service error. Please try again later.',
                    $response->status() === 404 => 'Ollama model not found. Check OLLAMA_MODEL in your .env file.',
                    $response->status() === 401 => 'Ollama authentication failed. Check OLLAMA_API_KEY in your .env file.',
                    default => 'Ollama service error. Please try again.',
                };

                return ['error' => $errorMessage, 'status' => $response->status()];
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Ollama API exception', ['message' => $e->getMessage()]);

            return ['error' => "Cannot connect to Ollama at {$this->baseUrl}. Check your OLLAMA_BASE_URL and network connection."];
        }
    }
}
