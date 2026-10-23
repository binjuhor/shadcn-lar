<?php

namespace Modules\Settings\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class AllowedOllamaOrigin implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be an allowed Ollama origin.');

            return;
        }

        $origin = $this->normalize($value);
        $allowedOrigins = collect([
            config('services.ollama.base_url'),
            ...config('services.ollama.allowed_origins', []),
        ])->filter()
            ->map(fn (string $allowedOrigin): ?string => $this->normalize($allowedOrigin))
            ->filter()
            ->all();

        if ($origin === null || ! in_array($origin, $allowedOrigins, true)) {
            $fail('The :attribute must match an Ollama origin allowed by the server administrator.');
        }
    }

    private function normalize(string $value): ?string
    {
        $parts = parse_url(trim($value));

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return null;
        }

        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            return null;
        }

        if (isset($parts['path']) && ! in_array($parts['path'], ['', '/'], true)) {
            return null;
        }

        $host = strtolower($parts['host']);
        $host = str_contains($host, ':') ? "[{$host}]" : $host;
        $port = isset($parts['port']) ? ":{$parts['port']}" : '';

        return strtolower($parts['scheme'])."://{$host}{$port}";
    }
}
