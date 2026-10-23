<?php

namespace Modules\Finance\Policies;

use App\Models\User;
use Modules\Finance\Models\Loan;

class LoanPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Loan $loan): bool
    {
        return $user->id === $loan->user_id;
    }

    public function update(User $user, Loan $loan): bool
    {
        return $user->id === $loan->user_id;
    }

    public function delete(User $user, Loan $loan): bool
    {
        return $user->id === $loan->user_id;
    }
}
