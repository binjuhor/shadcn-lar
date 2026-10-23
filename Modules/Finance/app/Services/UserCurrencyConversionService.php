<?php

namespace Modules\Finance\Services;

use App\Models\User;
use Modules\Finance\Models\Currency;

class UserCurrencyConversionService
{
    /** @var array<int, string> */
    protected array $defaultCurrencies = [];

    /** @var array<string, ?float> */
    protected array $rates = [];

    public function __construct(
        protected ExchangeRateService $exchangeRateService
    ) {}

    /**
     * @return array{
     *     converted_amount: ?float,
     *     converted_currency_code: string,
     *     exchange_rate: ?float,
     *     conversion_available: bool
     * }
     */
    public function convert(float $amount, string $fromCurrency, User $user, ?string $source = null): array
    {
        $toCurrency = $this->defaultCurrency($user);

        if ($fromCurrency === $toCurrency) {
            return $this->result($amount, $toCurrency, 1.0, true);
        }

        $source ??= $user->finance_settings['default_exchange_rate_source'] ?? null;
        $rate = $this->rate($fromCurrency, $toCurrency, $source);

        if ($rate === null) {
            return $this->result(null, $toCurrency, null, false);
        }

        return $this->result($amount * $rate, $toCurrency, $rate, true);
    }

    public function defaultCurrency(User $user): string
    {
        if (isset($this->defaultCurrencies[$user->id])) {
            return $this->defaultCurrencies[$user->id];
        }

        $settings = $user->finance_settings ?? [];

        return $this->defaultCurrencies[$user->id] = $settings['default_currency']
            ?? Currency::where('is_default', true)->value('code')
            ?? 'VND';
    }

    protected function rate(string $fromCurrency, string $toCurrency, ?string $source): ?float
    {
        $key = "{$fromCurrency}:{$toCurrency}:{$source}";

        if (array_key_exists($key, $this->rates)) {
            return $this->rates[$key];
        }

        return $this->rates[$key] = $this->exchangeRateService->getRate(
            $fromCurrency,
            $toCurrency,
            $source
        );
    }

    /**
     * @return array{
     *     converted_amount: ?float,
     *     converted_currency_code: string,
     *     exchange_rate: ?float,
     *     conversion_available: bool
     * }
     */
    protected function result(?float $amount, string $currencyCode, ?float $rate, bool $available): array
    {
        return [
            'converted_amount' => $amount,
            'converted_currency_code' => $currencyCode,
            'exchange_rate' => $rate,
            'conversion_available' => $available,
        ];
    }
}
