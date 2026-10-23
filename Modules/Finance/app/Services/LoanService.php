<?php

namespace Modules\Finance\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Finance\Models\{Loan, LoanRepayment, Transaction};

class LoanService
{
    public function __construct(protected TransactionService $transactions) {}

    public function createLoan(array $data): Loan
    {
        return Loan::create([
            'user_id' => $data['user_id'] ?? auth()->id(),
            'transaction_id' => $data['transaction_id'] ?? null,
            'borrower_name' => $data['borrower_name'],
            'amount' => $data['amount'],
            'amount_repaid' => 0,
            'currency_code' => $data['currency_code'],
            'loan_date' => $data['loan_date'],
            'due_date' => $data['due_date'] ?? null,
            'message' => $data['message'] ?? null,
            'status' => 'active',
        ]);
    }

    public function updateLoan(Loan $loan, array $data): Loan
    {
        return DB::transaction(function () use ($loan, $data) {
            $lockedLoan = Loan::query()->lockForUpdate()->findOrFail($loan->id);
            $repaid = (float) $lockedLoan->repayments()->sum('amount');
            $amount = (float) ($data['amount'] ?? $lockedLoan->amount);

            if ($amount < $repaid) {
                throw ValidationException::withMessages([
                    'amount' => 'The amount cannot be less than the amount already repaid.',
                ]);
            }

            if (isset($data['currency_code'])
                && $data['currency_code'] !== $lockedLoan->currency_code
                && $repaid > 0) {
                throw ValidationException::withMessages([
                    'currency_code' => 'The currency cannot change after a repayment has been recorded.',
                ]);
            }

            $lockedLoan->update($data);
            $this->recalculateStatus($lockedLoan);

            return $lockedLoan->fresh();
        });
    }

    public function addRepayment(Loan $loan, array $data): LoanRepayment
    {
        return DB::transaction(function () use ($loan, $data) {
            $lockedLoan = Loan::query()->lockForUpdate()->findOrFail($loan->id);
            $amount = abs((float) $data['amount']);
            $repaid = (float) $lockedLoan->repayments()->sum('amount');
            $remaining = max((float) $lockedLoan->amount - $repaid, 0);

            if (! in_array($lockedLoan->status, ['active', 'partially_repaid'], true)) {
                throw ValidationException::withMessages([
                    'amount' => 'Repayments can only be added to an outstanding loan.',
                ]);
            }

            if ($amount <= 0 || $amount > $remaining) {
                throw ValidationException::withMessages([
                    'amount' => 'The repayment cannot exceed the remaining principal.',
                ]);
            }

            $repayment = $lockedLoan->repayments()->create([
                'amount' => $amount,
                'repaid_at' => $data['repaid_at'] ?? now()->toDateString(),
                'note' => $data['note'] ?? null,
            ]);

            $this->recalculateStatus($lockedLoan);

            return $repayment;
        });
    }

    public function deleteRepayment(LoanRepayment $repayment): void
    {
        DB::transaction(function () use ($repayment) {
            $loan = Loan::query()->lockForUpdate()->findOrFail($repayment->loan_id);
            // Undo the account movement recorded together with this repayment
            if ($repayment->transaction_id && $transaction = Transaction::find($repayment->transaction_id)) {
                $this->transactions->reverseAndDelete($transaction);
            }

            $repayment->forceDelete();
            $this->recalculateStatus($loan);
        });
    }

    public function recalculateStatus(Loan $loan): void
    {
        $loan->refresh();
        $repaid = (float) $loan->repayments()->sum('amount');

        $status = match (true) {
            $loan->status === 'written_off' => 'written_off',
            $repaid >= $loan->amount && $loan->amount > 0 => 'repaid',
            $repaid > 0 => 'partially_repaid',
            default => 'active',
        };

        $loan->update([
            'amount_repaid' => $repaid,
            'status' => $status,
        ]);
    }
}
