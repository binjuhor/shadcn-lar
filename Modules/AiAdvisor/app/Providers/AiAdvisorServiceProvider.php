<?php

namespace Modules\AiAdvisor\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\AiAdvisor\Contracts\AiProvider;
use Modules\AiAdvisor\Providers\Ai\ConfiguredAiProvider;
use Modules\AiAdvisor\Services\FinanceAdvisorRuntime;
use Modules\AiAdvisor\Services\ToolRegistry;
use Modules\AiAdvisor\Tools\Finance\{DraftTransactionsTool, FinanceToolCatalog};
use Modules\Finance\Contracts\AdvisorRuntime;

class AiAdvisorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(module_path('AiAdvisor', 'config/config.php'), 'aiadvisor');
        $this->app->singleton(ToolRegistry::class);
        $this->app->bind(AiProvider::class, ConfiguredAiProvider::class);
        $this->app->bind(AdvisorRuntime::class, FinanceAdvisorRuntime::class);
    }

    public function boot(): void
    {
        $registry = $this->app->make(ToolRegistry::class);

        foreach (FinanceToolCatalog::classes() as $toolClass) {
            $registry->register($this->app->make($toolClass));
        }

        // Assistant-only tool; the advisor agent does not advertise it
        $registry->register($this->app->make(DraftTransactionsTool::class));
    }
}
