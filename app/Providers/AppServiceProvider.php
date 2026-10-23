<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        $this->configureRateLimiting();
    }

    /**
     * Named limiters referenced by module API routes (throttle:api-*).
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api-ai', fn (Request $request) => Limit::perMinute(20)->by($request->user()?->id ?? $request->ip())
        );

        RateLimiter::for('api-general', fn (Request $request) => Limit::perMinute(300)->by($request->user()?->id ?? $request->ip())
        );

        RateLimiter::for('api-reports', fn (Request $request) => Limit::perMinute(30)->by($request->user()?->id ?? $request->ip())
        );
    }
}
