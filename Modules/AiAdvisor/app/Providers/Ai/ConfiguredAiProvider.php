<?php

namespace Modules\AiAdvisor\Providers\Ai;

use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\AiAdvisor\Contracts\AiProvider;
use Modules\Settings\Services\AiCredentialResolver;
use RuntimeException;

class ConfiguredAiProvider implements AiProvider
{
    public function complete(string $systemPrompt, array $messages, array $tools, ?User $actor = null, ?string $provider = null): array
    {
        // An explicit provider means per-user selection, so Ollama also uses the user's URL/model
        $perUser = $provider !== null;
        $provider ??= config('services.smart_input.provider', 'deepseek');

        return match ($provider) {
            'claude' => $this->claude($systemPrompt, $messages, $tools, $actor),
            'gemini' => $this->gemini($systemPrompt, $messages, $tools, $actor),
            'deepseek', 'openai', 'openrouter', 'ollama' => $this->openAiCompatible($provider, $systemPrompt, $messages, $tools, $actor, $perUser),
            default => throw new RuntimeException("Unsupported AI advisor provider: {$provider}"),
        };
    }

    private function openAiCompatible(string $provider, string $systemPrompt, array $messages, array $tools, ?User $actor, bool $perUser = false): array
    {
        $resolver = app(AiCredentialResolver::class);
        $ollamaBaseUrl = $perUser ? $resolver->ollamaBaseUrl($actor) : rtrim((string) config('services.ollama.base_url', 'http://localhost:11434'), '/');
        $ollamaModel = $perUser ? $resolver->ollamaModel($actor) : config('services.ollama.model', 'llama3.2');

        [$url, $model, $request] = match ($provider) {
            'deepseek' => ['https://api.deepseek.com/v1/chat/completions', config('services.deepseek.model', 'deepseek-chat'), Http::withToken((string) app(AiCredentialResolver::class)->apiKey('deepseek', $actor))],
            'openai' => ['https://api.openai.com/v1/chat/completions', config('services.openai.model', 'gpt-4o-mini'), Http::withToken((string) app(AiCredentialResolver::class)->apiKey('openai', $actor))],
            'openrouter' => ['https://openrouter.ai/api/v1/chat/completions', config('services.openrouter.model', 'openrouter/auto'), Http::withToken((string) app(AiCredentialResolver::class)->apiKey('openrouter', $actor))->withHeaders(['HTTP-Referer' => config('app.url'), 'X-Title' => config('app.name')])],
            'ollama' => [$ollamaBaseUrl.'/v1/chat/completions', $ollamaModel, $this->ollamaRequest($actor)],
        };

        $payload = [
            'model' => $model,
            'messages' => [['role' => 'system', 'content' => $systemPrompt], ...$this->openAiMessages($messages)],
            'tools' => collect($tools)->map(fn (array $tool) => ['type' => 'function', 'function' => [
                'name' => $tool['name'],
                'description' => $tool['description'],
                'parameters' => $tool['input_schema'],
            ]])->all(),
            'temperature' => 0.3,
        ];

        $response = $request->timeout($provider === 'ollama' ? 120 : 60)->post($url, $payload);

        // Some Ollama builds accept the initial tool declaration but reject the
        // follow-up OpenAI-compatible tool-result message. The tool result is
        // already in the conversation, so retry without the tool-calling framing
        // (drop `tools`, strip `tool_calls`, and fold tool results into plain
        // user messages) to let the model produce a final natural-language answer.
        if ($provider === 'ollama'
            && in_array($response->status(), [400, 413, 422], true)
            && $this->containsToolResult($messages)) {
            Log::warning('Ollama rejected tool-result round; retrying without tools', [
                'status' => $response->status(),
                'body' => $response->json() ?: $response->body(),
            ]);
            $payload['tools'] = [];
            $payload['messages'] = $this->stripToolCalling($payload['messages']);
            $response = $request->timeout(120)->post($url, $payload);
        }

        $response->throw();
        $data = $response->json();
        $message = $data['choices'][0]['message'] ?? [];

        return [
            'content' => $message['content'] ?? '',
            'tool_calls' => collect($message['tool_calls'] ?? [])->map(fn (array $call) => [
                'id' => $call['id'] ?? uniqid('tool_', true),
                'name' => $call['function']['name'] ?? '',
                'arguments' => json_decode($call['function']['arguments'] ?? '{}', true) ?: [],
            ])->all(),
            'usage' => [
                'prompt_tokens' => $data['usage']['prompt_tokens'] ?? null,
                'completion_tokens' => $data['usage']['completion_tokens'] ?? null,
            ],
        ];
    }

    /** @param array<int, array<string, mixed>> $messages */
    private function containsToolResult(array $messages): bool
    {
        return collect($messages)->contains(fn (array $message): bool => ($message['role'] ?? null) === 'tool');
    }

    /**
     * Remove tool-calling framing from already-built OpenAI messages so a
     * degraded retry is a plain chat completion: strip `tool_calls` from
     * assistant messages and fold `tool` results into user messages.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @return array<int, array<string, mixed>>
     */
    private function stripToolCalling(array $messages): array
    {
        return collect($messages)->flatMap(function (array $message): array {
            if (($message['role'] ?? null) === 'tool') {
                $name = $message['name'] ?? 'tool';

                return [['role' => 'user', 'content' => "Tool result for {$name}: ".($message['content'] ?? '')]];
            }

            if (($message['role'] ?? null) === 'assistant' && isset($message['tool_calls'])) {
                return [['role' => 'assistant', 'content' => $message['content'] ?? '']];
            }

            return [$message];
        })->values()->all();
    }

    /**
     * JSON-encode tool-call arguments, preserving an empty object as "{}".
     *
     * Ollama's OpenAI-compatible validator rejects "[]" for a function whose
     * parameters schema is `{"type":"object"}` — PHP's json_encode turns an empty
     * associative array into "[]", which round-trips a no-arg tool call as an
     * invalid array. Encode empty arguments as "{}" so the echo-back is valid.
     */
    private function encodeArguments(array $arguments): string
    {
        return $arguments === [] ? '{}' : json_encode($arguments, JSON_THROW_ON_ERROR);
    }

    private function claude(string $systemPrompt, array $messages, array $tools, ?User $actor): array
    {
        $response = Http::withHeaders([
            'x-api-key' => app(AiCredentialResolver::class)->apiKey('claude', $actor),
            'anthropic-version' => '2023-06-01',
        ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
            'model' => config('services.claude.model', 'claude-sonnet-4-20250514'),
            'max_tokens' => 2048,
            'system' => $systemPrompt,
            'messages' => $this->claudeMessages($messages),
            'tools' => collect($tools)->map(fn (array $tool) => [
                'name' => $tool['name'],
                'description' => $tool['description'],
                'input_schema' => $tool['input_schema'],
            ])->all(),
        ]);
        $response->throw();
        $data = $response->json();

        return [
            'content' => collect($data['content'] ?? [])->where('type', 'text')->pluck('text')->implode("\n"),
            'tool_calls' => collect($data['content'] ?? [])->where('type', 'tool_use')->map(fn (array $block) => [
                'id' => $block['id'], 'name' => $block['name'], 'arguments' => $block['input'] ?? [],
            ])->values()->all(),
            'usage' => ['prompt_tokens' => $data['usage']['input_tokens'] ?? null, 'completion_tokens' => $data['usage']['output_tokens'] ?? null],
        ];
    }

    private function gemini(string $systemPrompt, array $messages, array $tools, ?User $actor): array
    {
        $model = config('services.gemini.model', 'gemini-2.0-flash');
        $key = app(AiCredentialResolver::class)->apiKey('gemini', $actor);
        $response = Http::timeout(60)->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}", [
            'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
            'contents' => $this->geminiMessages($messages),
            'tools' => [['functionDeclarations' => collect($tools)->map(fn (array $tool) => [
                'name' => $tool['name'], 'description' => $tool['description'], 'parameters' => $this->geminiSchema($tool['input_schema']),
            ])->all()]],
        ]);
        $response->throw();
        $data = $response->json();
        $parts = $data['candidates'][0]['content']['parts'] ?? [];

        return [
            'content' => collect($parts)->pluck('text')->filter()->implode("\n"),
            'tool_calls' => collect($parts)->filter(fn (array $part) => isset($part['functionCall']))->map(fn (array $part) => [
                'id' => uniqid('gemini_', true), 'name' => $part['functionCall']['name'], 'arguments' => $part['functionCall']['args'] ?? [],
            ])->values()->all(),
            'usage' => ['prompt_tokens' => $data['usageMetadata']['promptTokenCount'] ?? null, 'completion_tokens' => $data['usageMetadata']['candidatesTokenCount'] ?? null],
        ];
    }

    private function ollamaRequest(?User $actor): PendingRequest
    {
        $request = Http::withHeaders(['Content-Type' => 'application/json']);
        $key = app(AiCredentialResolver::class)->apiKey('ollama', $actor);

        return $key ? $request->withToken($key) : $request;
    }

    private function claudeMessages(array $messages): array
    {
        return collect($messages)->map(function (array $message): array {
            if ($message['role'] === 'tool') {
                return ['role' => 'user', 'content' => [['type' => 'tool_result', 'tool_use_id' => $message['tool_call_id'], 'content' => $message['content']]]];
            }

            if ($message['role'] === 'assistant' && isset($message['tool_calls'])) {
                $content = [];
                if ($message['content'] !== '') {
                    $content[] = ['type' => 'text', 'text' => $message['content']];
                }

                foreach ($message['tool_calls'] as $call) {
                    $content[] = ['type' => 'tool_use', 'id' => $call['id'], 'name' => $call['name'], 'input' => $call['arguments'] ?? []];
                }

                return ['role' => 'assistant', 'content' => $content];
            }

            return ['role' => $message['role'], 'content' => $message['content'] ?: ''];
        })->all();
    }

    private function geminiMessages(array $messages): array
    {
        return collect($messages)->map(function (array $message): array {
            if ($message['role'] === 'tool') {
                return ['role' => 'function', 'parts' => [['functionResponse' => ['name' => $message['name'], 'response' => json_decode($message['content'], true)]]]];
            }

            if ($message['role'] === 'assistant' && isset($message['tool_calls'])) {
                $parts = [];
                if ($message['content'] !== '') {
                    $parts[] = ['text' => $message['content']];
                }

                foreach ($message['tool_calls'] as $call) {
                    $parts[] = ['functionCall' => ['name' => $call['name'], 'args' => $call['arguments'] ?? []]];
                }

                return ['role' => 'model', 'parts' => $parts];
            }

            return ['role' => $message['role'] === 'assistant' ? 'model' : 'user', 'parts' => [['text' => $message['content'] ?: 'Continue.']]];
        })->all();
    }

    private function openAiMessages(array $messages): array
    {
        return collect($messages)->map(function (array $message): array {
            if ($message['role'] !== 'assistant' || ! isset($message['tool_calls'])) {
                return $message;
            }

            return [
                'role' => 'assistant',
                'content' => $message['content'],
                'tool_calls' => collect($message['tool_calls'])->map(fn (array $call) => [
                    'id' => $call['id'],
                    'type' => 'function',
                    'function' => [
                        'name' => $call['name'],
                        'arguments' => $this->encodeArguments($call['arguments'] ?? []),
                    ],
                ])->all(),
            ];
        })->all();
    }

    /** @param array<string, mixed> $schema */
    private function geminiSchema(array $schema): array
    {
        unset($schema['additionalProperties']);

        if (isset($schema['type'])) {
            $type = is_array($schema['type'])
                ? collect($schema['type'])->first(fn (string $value) => $value !== 'null')
                : $schema['type'];
            $schema['type'] = strtoupper((string) $type);
        }

        if (isset($schema['properties'])) {
            $schema['properties'] = collect($schema['properties'])
                ->map(fn (array $property) => $this->geminiSchema($property))
                ->all();
        }

        if (isset($schema['items'])) {
            $schema['items'] = $this->geminiSchema($schema['items']);
        }

        return $schema;
    }
}
