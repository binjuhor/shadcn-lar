<?php

namespace Modules\Finance\Console\Commands;

use Illuminate\Console\Command;
use Modules\Finance\Services\ExchangeRateService;

class FetchExchangeRatesCommand extends Command
{
    protected $signature = 'finance:fetch-exchange-rates
                            {--provider=vietcombank : Rate provider (exchangerate_api, open_exchange_rates, vietcombank)}
                            {--all : Fetch from all configured providers}';

    protected $description = 'Fetch latest exchange rates from external providers';

    public function handle(ExchangeRateService $service): int
    {
        $providers = $this->option('all')
            ? ['exchangerate_api', 'open_exchange_rates', 'vietcombank']
            : [$this->option('provider')];

        $totalCount = 0;

        foreach ($providers as $provider) {
            $this->info("Fetching rates from {$provider}...");

            try {
                $count = $service->updateRates($provider);
                $totalCount += $count;
                $this->info("  Updated {$count} rates from {$provider}");
            } catch (\Exception $e) {
                $this->error("  Failed to fetch from {$provider}: {$e->getMessage()}");
            }
        }

        $this->info("Total rates updated: {$totalCount}");

        return self::SUCCESS;
    }
}
