<?php

namespace Modules\Invoice\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [];

    protected static $shouldDiscoverEvents = true;

    protected function discoverEventsWithin(): array
    {
        return [
            module_path('Invoice', 'Listeners'),
        ];
    }
}
