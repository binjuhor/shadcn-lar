<?php

namespace Modules\Finance\Services;

use Illuminate\Support\Facades\{Http, Log};
use Modules\Finance\Models\{Account, Currency, ExchangeRate};

class ExchangeRateService
{
    protected array $providers = [
        'exchangerate_api' => 'fetchFromExchangeRateApi',
        'open_exchange_rates' => 'fetchFromOpenExchangeRates',
        'vietcombank' => 'fetchFromVietcombank',
        'payoneer' => 'fetchFromPayoneer',
    ];

    protected const PAYONEER_FEE_PERCENT = 0.5;

    protected const PAYONEER_CURRENCIES = ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY', 'CNH', 'VND'];

    /**
     * Convert amount from one currency to another
     *
     * @param  float  $amount  Amount to convert
     * @param  string  $from  Source currency code
     * @param  string  $to  Target currency code
     * @param  string|null  $source  Preferred rate source (e.g., 'payoneer', 'vietcombank')
     */
    public function convert(float $amount, string $from, string $to, ?string $source = null): float
    {
        if ($from === $to) {
            return $amount;
        }

        $rate = $this->getRate($from, $to, $source);

        if (! $rate) {
            throw new \RuntimeException("Exchange rate not found for {$from}/{$to}");
        }

        return $amount * $rate;
    }

    /**
     * Get exchange rate between two currencies
     *
     * @param  string  $from  Source currency code
     * @param  string  $to  Target currency code
     * @param  string|null  $source  Preferred rate source (null = best available)
     */
    public function getRate(string $from, string $to, ?string $source = null): ?float
    {
        $exchangeRate = ExchangeRate::getLatestRate($from, $to, $source);

        if ($exchangeRate) {
            return (float) $exchangeRate->rate;
        }

        $reverseRate = ExchangeRate::getLatestRate($to, $from, $source);

        if ($reverseRate && $reverseRate->rate > 0) {
            return 1 / (float) $reverseRate->rate;
        }

        // Fallback to any available source if specific source not found
        if ($source !== null) {
            return $this->getRate($from, $to, null);
        }

        return null;
    }

    /**
     * Convert amount to default currency
     *
     * @param  float  $amount  Amount to convert
     * @param  string  $from  Source currency code
     * @param  string|null  $source  Preferred rate source
     */
    public function convertToDefault(float $amount, string $from, ?string $source = null): float
    {
        $defaultCurrency = Currency::where('is_default', true)->first();

        if (! $defaultCurrency || $defaultCurrency->code === $from) {
            return $amount;
        }

        return $this->convert($amount, $from, $defaultCurrency->code, $source);
    }

    /**
     * Get currencies used by user's accounts
     *
     * @param  int|null  $userId  User ID, null for all users
     * @param  bool  $includeDefault  Include default currency
     */
    public function getAccountCurrencies(?int $userId = null, bool $includeDefault = true): array
    {
        $query = Account::query();

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $currencies = $query->distinct()->pluck('currency_code')->toArray();

        if ($includeDefault) {
            $defaultCurrency = Currency::where('is_default', true)->value('code');
            if ($defaultCurrency && ! in_array($defaultCurrency, $currencies)) {
                $currencies[] = $defaultCurrency;
            }
        }

        return array_unique($currencies);
    }

    /**
     * @param  array|null  $currencies  Only fetch rates for these currencies (null = all)
     */
    public function fetchRates(string $provider = 'exchangerate_api', ?array $currencies = null): array
    {
        if (! isset($this->providers[$provider])) {
            throw new \InvalidArgumentException("Unknown provider: {$provider}");
        }

        $method = $this->providers[$provider];

        return $this->$method($currencies);
    }

    /**
     * @param  bool  $accountOnly  Only fetch rates for currencies used in accounts
     * @param  int|null  $userId  Filter by user's accounts (null for all users)
     */
    public function updateRates(string $provider = 'exchangerate_api', bool $accountOnly = false, ?int $userId = null): int
    {
        $currencies = null;

        if ($accountOnly) {
            $currencies = $this->getAccountCurrencies($userId);

            if (empty($currencies)) {
                return 0;
            }
        }

        $rates = $this->fetchRates($provider, $currencies);
        $count = 0;

        foreach ($rates as $rateData) {
            ExchangeRate::updateOrCreate(
                [
                    'base_currency' => $rateData['base'],
                    'target_currency' => $rateData['target'],
                    'source' => $provider,
                    'rate_date' => $rateData['date'] ?? now(),
                ],
                [
                    'rate' => $rateData['rate'],
                    'bid_rate' => $rateData['bid'] ?? null,
                    'ask_rate' => $rateData['ask'] ?? null,
                ]
            );
            $count++;
        }

        return $count;
    }

    protected function fetchFromExchangeRateApi(?array $filterCurrencies = null): array
    {
        $apiKey = config('services.exchangerate_api.key');
        $baseCurrency = config('services.exchangerate_api.base', 'USD');

        if (! $apiKey) {
            Log::warning('ExchangeRate API key not configured');

            return [];
        }

        $response = Http::get("https://v6.exchangerate-api.com/v6/{$apiKey}/latest/{$baseCurrency}");

        if (! $response->successful()) {
            Log::error('Failed to fetch exchange rates from ExchangeRate API', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [];
        }

        $data = $response->json();
        $rates = [];
        $allowedCurrencies = $filterCurrencies ?? Currency::pluck('code')->toArray();

        foreach ($data['conversion_rates'] ?? [] as $currency => $rate) {
            if (in_array($currency, $allowedCurrencies) && $currency !== $baseCurrency) {
                $rates[] = [
                    'base' => $baseCurrency,
                    'target' => $currency,
                    'rate' => $rate,
                    'date' => now(),
                ];
            }
        }

        return $rates;
    }

    protected function fetchFromOpenExchangeRates(?array $filterCurrencies = null): array
    {
        $apiKey = config('services.open_exchange_rates.key');
        $baseCurrency = config('services.open_exchange_rates.base', 'USD');

        if (! $apiKey) {
            Log::warning('Open Exchange Rates API key not configured');

            return [];
        }

        $response = Http::get('https://openexchangerates.org/api/latest.json', [
            'app_id' => $apiKey,
            'base' => $baseCurrency,
        ]);

        if (! $response->successful()) {
            Log::error('Failed to fetch exchange rates from Open Exchange Rates', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [];
        }

        $data = $response->json();
        $rates = [];
        $allowedCurrencies = $filterCurrencies ?? Currency::pluck('code')->toArray();

        foreach ($data['rates'] ?? [] as $currency => $rate) {
            if (in_array($currency, $allowedCurrencies) && $currency !== $baseCurrency) {
                $rates[] = [
                    'base' => $baseCurrency,
                    'target' => $currency,
                    'rate' => $rate,
                    'date' => now(),
                ];
            }
        }

        return $rates;
    }

    protected function fetchFromVietcombank(?array $filterCurrencies = null): array
    {
        $response = Http::get('https://portal.vietcombank.com.vn/Usercontrols/TVPortal.TyGia/pXML.aspx');

        if (! $response->successful()) {
            Log::error('Failed to fetch exchange rates from Vietcombank', [
                'status' => $response->status(),
            ]);

            return [];
        }

        $rates = [];
        $xml = simplexml_load_string($response->body());

        if (! $xml) {
            Log::error('Failed to parse Vietcombank XML response');

            return [];
        }

        $allowedCurrencies = $filterCurrencies ?? Currency::pluck('code')->toArray();

        foreach ($xml->Exrate as $exrate) {
            $currency = (string) $exrate['CurrencyCode'];
            $buy = str_replace(',', '', (string) $exrate['Buy']);
            $transfer = str_replace(',', '', (string) $exrate['Transfer']);
            $sell = str_replace(',', '', (string) $exrate['Sell']);

            if ($currency && $transfer && in_array($currency, $allowedCurrencies) && in_array('VND', $allowedCurrencies)) {
                $rates[] = [
                    'base' => $currency,
                    'target' => 'VND',
                    'rate' => (float) $transfer,
                    'bid' => $buy ? (float) $buy : null,
                    'ask' => $sell ? (float) $sell : null,
                    'date' => now(),
                ];
            }
        }

        return $rates;
    }

    /**
     * Fetch exchange rates with Payoneer's 0.5% fee markup
     *
     * Payoneer doesn't provide a public API for exchange rates.
     * This calculates approximate Payoneer rates by applying their
     * 0.5% fee to market rates from ExchangeRate-API.
     *
     * Supported currencies: USD, EUR, GBP, CAD, AUD, JPY, CNH, VND
     *
     * @see https://www.payoneer.com/manage-currencies/
     */
    protected function fetchFromPayoneer(?array $filterCurrencies = null): array
    {
        $apiKey = config('services.exchangerate_api.key');

        if (! $apiKey) {
            Log::warning('Payoneer rates: ExchangeRate API key not configured (needed for base rates)');

            return $this->calculatePayoneerRatesFromExisting($filterCurrencies);
        }

        $rates = [];
        $allowedCurrencies = $filterCurrencies ?? Currency::pluck('code')->toArray();
        $payoneerCurrencies = array_intersect(self::PAYONEER_CURRENCIES, $allowedCurrencies);

        foreach (['USD', 'EUR'] as $baseCurrency) {
            if (! in_array($baseCurrency, $payoneerCurrencies)) {
                continue;
            }

            $response = Http::get("https://v6.exchangerate-api.com/v6/{$apiKey}/latest/{$baseCurrency}");

            if (! $response->successful()) {
                Log::warning("Failed to fetch {$baseCurrency} rates for Payoneer calculation");

                continue;
            }

            $data = $response->json();

            foreach ($data['conversion_rates'] ?? [] as $targetCurrency => $rate) {
                if (! in_array($targetCurrency, $payoneerCurrencies) || $targetCurrency === $baseCurrency) {
                    continue;
                }

                $payoneerRate = $this->applyPayoneerFee($rate);

                $rates[] = [
                    'base' => $baseCurrency,
                    'target' => $targetCurrency,
                    'rate' => $payoneerRate,
                    'date' => now(),
                ];
            }
        }

        return $rates;
    }

    protected function applyPayoneerFee(float $rate): float
    {
        return $rate * (1 - self::PAYONEER_FEE_PERCENT / 100);
    }

    protected function calculatePayoneerRatesFromExisting(?array $filterCurrencies = null): array
    {
        $rates = [];
        $allowedCurrencies = $filterCurrencies ?? Currency::pluck('code')->toArray();
        $payoneerCurrencies = array_intersect(self::PAYONEER_CURRENCIES, $allowedCurrencies);

        $existingRates = ExchangeRate::whereIn('base_currency', $payoneerCurrencies)
            ->whereIn('target_currency', $payoneerCurrencies)
            ->where('source', '!=', 'payoneer')
            ->latest('rate_date')
            ->get()
            ->unique(fn ($r) => $r->base_currency.'-'.$r->target_currency);

        foreach ($existingRates as $existingRate) {
            $rates[] = [
                'base' => $existingRate->base_currency,
                'target' => $existingRate->target_currency,
                'rate' => $this->applyPayoneerFee((float) $existingRate->rate),
                'date' => now(),
            ];
        }

        return $rates;
    }
}
