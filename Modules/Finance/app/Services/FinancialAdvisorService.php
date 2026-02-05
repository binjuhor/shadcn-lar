<?php

namespace Modules\Finance\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\Finance\Models\{AdvisorConversation, AdvisorMessage};

class FinancialAdvisorService
{
    public function __construct(
        protected FinancialContextBuilder $contextBuilder
    ) {}

    public function isConfigured(): bool
    {
        $provider = config('services.smart_input.provider', 'deepseek');

        return (bool) match ($provider) {
            'gemini' => config('services.gemini.api_key'),
            'claude' => config('services.claude.api_key'),
            'deepseek' => config('services.deepseek.api_key'),
            default => false,
        };
    }

    public function sendMessage(AdvisorConversation $conversation, string $userContent, int $userId): AdvisorMessage
    {
        // Store user message
        $conversation->messages()->create([
            'role' => 'user',
            'content' => $userContent,
        ]);

        // Auto-title from first message
        if (! $conversation->title) {
            $conversation->update([
                'title' => Str::limit($userContent, 60),
            ]);
        }

        // Build system prompt with financial context
        $financialContext = $this->contextBuilder->build($userId);
        $systemPrompt = $this->buildSystemPrompt($financialContext);

        // Load recent conversation history
        $history = $conversation->messages()
            ->orderBy('created_at')
            ->limit(20)
            ->get()
            ->map(fn (AdvisorMessage $m) => [
                'role' => $m->role,
                'content' => $m->content,
            ])
            ->toArray();

        // Call AI API
        $provider = config('services.smart_input.provider', 'deepseek');
        $response = $this->callApi($systemPrompt, $history, $provider);

        // Store assistant message
        return $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $response['content'],
            'ai_provider' => $provider,
            'prompt_tokens' => $response['prompt_tokens'] ?? null,
            'completion_tokens' => $response['completion_tokens'] ?? null,
        ]);
    }

    protected function callApi(string $systemPrompt, array $messages, string $provider): array
    {
        return match ($provider) {
            'deepseek' => $this->callDeepSeek($systemPrompt, $messages),
            'claude' => $this->callClaude($systemPrompt, $messages),
            'gemini' => $this->callGemini($systemPrompt, $messages),
            default => throw new \RuntimeException("Unsupported provider: {$provider}"),
        };
    }

    protected function callDeepSeek(string $systemPrompt, array $messages): array
    {
        $apiKey = config('services.deepseek.api_key');
        $model = config('services.deepseek.model', 'deepseek-chat');

        $apiMessages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ...$messages,
        ];

        $response = Http::timeout(60)
            ->withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type' => 'application/json',
            ])
            ->post('https://api.deepseek.com/v1/chat/completions', [
                'model' => $model,
                'max_tokens' => 2048,
                'messages' => $apiMessages,
                'temperature' => 0.7,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException("DeepSeek API error: {$response->status()}");
        }

        $data = $response->json();

        return [
            'content' => $data['choices'][0]['message']['content'] ?? '',
            'prompt_tokens' => $data['usage']['prompt_tokens'] ?? null,
            'completion_tokens' => $data['usage']['completion_tokens'] ?? null,
        ];
    }

    protected function callClaude(string $systemPrompt, array $messages): array
    {
        $apiKey = config('services.claude.api_key');
        $model = config('services.claude.model', 'claude-sonnet-4-20250514');

        $response = Http::timeout(60)
            ->withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $model,
                'max_tokens' => 2048,
                'system' => $systemPrompt,
                'messages' => $messages,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException("Claude API error: {$response->status()}");
        }

        $data = $response->json();

        return [
            'content' => $data['content'][0]['text'] ?? '',
            'prompt_tokens' => $data['usage']['input_tokens'] ?? null,
            'completion_tokens' => $data['usage']['output_tokens'] ?? null,
        ];
    }

    protected function callGemini(string $systemPrompt, array $messages): array
    {
        $apiKey = config('services.gemini.api_key');
        $model = config('services.gemini.model', 'gemini-2.0-flash');

        $contents = [];

        foreach ($messages as $msg) {
            $contents[] = [
                'role' => $msg['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $msg['content']]],
            ];
        }

        $response = Http::timeout(60)
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}", [
                'systemInstruction' => [
                    'parts' => [['text' => $systemPrompt]],
                ],
                'contents' => $contents,
                'generationConfig' => [
                    'temperature' => 0.7,
                    'maxOutputTokens' => 2048,
                ],
            ]);

        if ($response->failed()) {
            throw new \RuntimeException("Gemini API error: {$response->status()}");
        }

        $data = $response->json();

        return [
            'content' => $data['candidates'][0]['content']['parts'][0]['text'] ?? '',
            'prompt_tokens' => $data['usageMetadata']['promptTokenCount'] ?? null,
            'completion_tokens' => $data['usageMetadata']['candidatesTokenCount'] ?? null,
        ];
    }

    protected function buildSystemPrompt(string $financialContext): string
    {
        $today = now()->format('Y-m-d');

        return <<<PROMPT
You are a friendly, knowledgeable personal financial advisor. Your goal is to help users understand and improve their financial health.

## Rules
- ONLY reference the financial data provided below. Do not make up numbers.
- If you don't have enough data to answer, say so honestly.
- Give actionable, specific advice based on the user's actual numbers.
- Keep responses concise (2-4 paragraphs max) unless the user asks for detail.
- Respond in the same language the user writes in (Vietnamese or English).
- Use simple language, avoid jargon.
- When recommending actions, be specific: "Save 500,000 VND/month" not "save more".
- Format numbers clearly. Use bullet points for lists.
- Today's date: {$today}

## User's Financial Data
{$financialContext}
PROMPT;
    }
}
