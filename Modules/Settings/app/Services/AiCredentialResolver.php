<?php

namespace Modules\Settings\Services;

use App\Models\User;

class AiCredentialResolver
{
    /** @var list<string> */
    public const PROVIDERS = [
        'claude',
        'gemini',
        'deepseek',
        'openai',
        'openrouter',
        'ollama',
    ];

    public function apiKey(string $provider, ?User $user = null): ?string
    {
        $this->ensureSupported($provider);

        $user ??= auth()->user();
        $userKey = $user?->ai_settings[$provider] ?? null;

        if (is_string($userKey) && $userKey !== '') {
            return $userKey;
        }

        $fallback = config("services.{$provider}.api_key");

        return is_string($fallback) && $fallback !== '' ? $fallback : null;
    }

    public function provider(?User $user = null): string
    {
        $user ??= auth()->user();
        $provider = $user?->ai_settings['provider'] ?? null;

        if (is_string($provider) && in_array($provider, self::PROVIDERS, true) && $this->hasCredential($provider, $user)) {
            return $provider;
        }

        $fallback = (string) config('services.smart_input.provider', 'ollama');

        return in_array($fallback, self::PROVIDERS, true) ? $fallback : 'ollama';
    }

    public function ollamaBaseUrl(?User $user = null): string
    {
        $user ??= auth()->user();
        $baseUrl = $user?->ai_settings['ollama_base_url'] ?? null;

        if (is_string($baseUrl) && $baseUrl !== '') {
            return rtrim($baseUrl, '/');
        }

        return rtrim((string) config('services.ollama.base_url', 'http://localhost:11434'), '/');
    }

    public function ollamaModel(?User $user = null): string
    {
        $user ??= auth()->user();
        $model = $user?->ai_settings['ollama_model'] ?? null;

        if (is_string($model) && $model !== '') {
            return $model;
        }

        return (string) config('services.ollama.model', 'llama3.2');
    }

    /** @return array<string, array{stored: bool, fallback: bool, configured: bool}> */
    public function statuses(User $user): array
    {
        return collect(self::PROVIDERS)
            ->mapWithKeys(function (string $provider) use ($user): array {
                $stored = is_string($user->ai_settings[$provider] ?? null)
                    && $user->ai_settings[$provider] !== '';
                $fallback = is_string(config("services.{$provider}.api_key"))
                    && config("services.{$provider}.api_key") !== '';

                return [$provider => [
                    'stored' => $stored,
                    'fallback' => $fallback,
                    'configured' => $stored || $fallback || $provider === 'ollama',
                ]];
            })
            ->all();
    }

    private function hasCredential(string $provider, ?User $user): bool
    {
        if ($provider === 'ollama') {
            return true;
        }

        return $this->apiKey($provider, $user) !== null;
    }

    private function ensureSupported(string $provider): void
    {
        if (! in_array($provider, self::PROVIDERS, true)) {
            throw new \InvalidArgumentException("Unsupported AI provider: {$provider}");
        }
    }
}
