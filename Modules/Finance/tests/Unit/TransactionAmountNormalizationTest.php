<?php

namespace Modules\Finance\Tests\Unit;

use Modules\Finance\Traits\ParsesTransactionEntities;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TransactionAmountNormalizationTest extends TestCase
{
    #[DataProvider('amounts')]
    public function test_it_preserves_decimal_and_formatted_amounts(mixed $input, float $expected): void
    {
        $normalizer = new class
        {
            use ParsesTransactionEntities;

            public function normalize(mixed $amount): float
            {
                return $this->normalizeTransactionAmount($amount);
            }
        };

        $this->assertSame($expected, $normalizer->normalize($input));
    }

    /** @return array<string, array{mixed, float}> */
    public static function amounts(): array
    {
        return [
            'numeric decimal' => [0.67, 0.67],
            'dollar decimal' => ['$0.67', 0.67],
            'US formatted' => ['$1,234.56', 1234.56],
            'European formatted' => ['€1.234,56', 1234.56],
            'thousands comma' => ['150,000', 150000.0],
            'decimal Vietnamese shortcut' => ['1.5k', 1500.0],
        ];
    }
}
