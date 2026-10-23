<?php

namespace Modules\Finance\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Finance\Events\TransactionCreated;
use Modules\Finance\Listeners\SyncRecurringTransactionFromTransactionListener;
use Modules\Finance\Listeners\UpdateBudgetSpendingListener;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        TransactionCreated::class => [
            UpdateBudgetSpendingListener::class,
            SyncRecurringTransactionFromTransactionListener::class,
        ],
    ];

    /**
     * Indicates if events should be discovered.
     *
     * @var bool
     */
    protected static $shouldDiscoverEvents = true;

    /**
     * Configure the proper event listeners for email verification.
     */
    protected function configureEmailVerification(): void {}
}
