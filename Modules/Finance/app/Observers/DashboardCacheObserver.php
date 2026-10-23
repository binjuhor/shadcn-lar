<?php

namespace Modules\Finance\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardCacheObserver
{
    public function created(Model $model): void
    {
        $this->forgetCache($model);
    }

    public function updated(Model $model): void
    {
        $this->forgetCache($model);
    }

    public function deleted(Model $model): void
    {
        $this->forgetCache($model);
    }

    protected function forgetCache(Model $model): void
    {
        // Transaction, Account, and Budget all have user_id directly on the model
        $userId = $model->user_id ?? $model->loan?->user_id ?? $model->borrowing?->user_id;

        if ($userId) {
            DB::afterCommit(fn () => Cache::forget("dashboard.{$userId}"));
        }
    }
}
