<?php

namespace Modules\Finance\Policies;

use App\Models\User;
use Modules\Finance\Models\Borrowing;

class BorrowingPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Borrowing $borrowing): bool
    {
        return $user->id === $borrowing->user_id;
    }

    public function update(User $user, Borrowing $borrowing): bool
    {
        return $user->id === $borrowing->user_id;
    }

    public function delete(User $user, Borrowing $borrowing): bool
    {
        return $user->id === $borrowing->user_id;
    }
}
