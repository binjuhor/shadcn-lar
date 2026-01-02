<?php

namespace Modules\Finance\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Finance\Models\SavingsGoal;

class SavingsGoalCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public SavingsGoal $savingsGoal
    ) {}
}
