<?php

namespace Modules\Invoice\Policies;

use App\Models\User;
use Modules\Invoice\Models\Invoice;

class InvoicePolicy
{
    public function view(User $user, Invoice $invoice): bool
    {
        return $user->id === $invoice->user_id;
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $user->id === $invoice->user_id;
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->id === $invoice->user_id;
    }
}
