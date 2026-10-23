<?php

namespace Modules\Finance\Services;

use App\Models\User;
use Modules\Finance\Models\Borrowing;

class BorrowingPayableService
{
    public function __construct(protected UserCurrencyConversionService $conversionService) {}

    public function totalForUser(User $user): float
    {
        return Borrowing::query()
            ->where('user_id', $user->id)
            ->outstanding()
            ->selectRaw('currency_code, SUM(CASE WHEN amount > amount_repaid THEN amount - amount_repaid ELSE 0 END) as outstanding_amount')
            ->groupBy('currency_code')
            ->get()
            ->sum(function (Borrowing $borrowing) use ($user): float {
                $conversion = $this->conversionService->convert(
                    (float) $borrowing->outstanding_amount,
                    $borrowing->currency_code,
                    $user
                );

                return (float) ($conversion['converted_amount'] ?? $borrowing->outstanding_amount);
            });
    }
}
