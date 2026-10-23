<?php

namespace Modules\Finance\Services;

use App\Models\User;
use Modules\Finance\Models\Loan;

class LoanReceivableService
{
    public function __construct(protected UserCurrencyConversionService $conversionService) {}

    public function totalForUser(User $user): float
    {
        return Loan::query()
            ->where('user_id', $user->id)
            ->outstanding()
            ->selectRaw('currency_code, SUM(CASE WHEN amount > amount_repaid THEN amount - amount_repaid ELSE 0 END) as outstanding_amount')
            ->groupBy('currency_code')
            ->get()
            ->sum(function (Loan $loan) use ($user): float {
                $conversion = $this->conversionService->convert(
                    (float) $loan->outstanding_amount,
                    $loan->currency_code,
                    $user
                );

                return (float) ($conversion['converted_amount'] ?? $loan->outstanding_amount);
            });
    }
}
