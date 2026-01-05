<?php

namespace Modules\Finance\Services;

use InvalidArgumentException;
use Modules\Finance\Contracts\TransactionParserInterface;

class TransactionParserFactory
{
    public static function make(?string $provider = null): TransactionParserInterface
    {
        $provider = $provider ?? config('services.smart_input.provider', 'claude');

        return match ($provider) {
            'claude' => new ClaudeTransactionParser,
            'gemini' => new GeminiTransactionParser,
            'deepseek' => new DeepSeekTransactionParser,
            default => throw new InvalidArgumentException("Unknown parser provider: {$provider}"),
        };
    }
}
