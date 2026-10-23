<?php

namespace Modules\Finance\Services;

use InvalidArgumentException;
use Modules\Finance\Contracts\TransactionParserInterface;
use Modules\Settings\Services\AiCredentialResolver;

class TransactionParserFactory
{
    public static function make(?string $provider = null): TransactionParserInterface
    {
        $provider = $provider ?? app(AiCredentialResolver::class)->provider();

        return match ($provider) {
            'claude' => new ClaudeTransactionParser,
            'gemini' => new GeminiTransactionParser,
            'deepseek' => new DeepSeekTransactionParser,
            'ollama' => new OllamaTransactionParser,
            'openrouter' => new OpenRouterTransactionParser,
            'openai' => new OpenAiTransactionParser,
            default => throw new InvalidArgumentException("Unknown parser provider: {$provider}"),
        };
    }
}
