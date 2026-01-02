<?php

namespace Modules\Finance\Policies;

use App\Models\User;
use Modules\Finance\Models\SavingsGoal;

class SavingsGoalPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, SavingsGoal $savingsGoal): bool
    {
        return $user->id === $savingsGoal->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, SavingsGoal $savingsGoal): bool
    {
        return $user->id === $savingsGoal->user_id;
    }

    public function delete(User $user, SavingsGoal $savingsGoal): bool
    {
        return $user->id === $savingsGoal->user_id;
    }

    public function contribute(User $user, SavingsGoal $savingsGoal): bool
    {
        return $user->id === $savingsGoal->user_id && $savingsGoal->isActive();
    }
}
