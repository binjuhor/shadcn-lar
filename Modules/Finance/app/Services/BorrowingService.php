<?php

namespace Modules\Finance\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Finance\Models\{Borrowing, BorrowingRepayment, Transaction};

class BorrowingService
{
    public function __construct(protected TransactionService $transactions) {}

    public function createBorrowing(array $data): Borrowing
    {
        return Borrowing::create([
            'user_id' => $data['user_id'] ?? auth()->id(),
            'transaction_id' => $data['transaction_id'] ?? null,
            'lender_name' => $data['lender_name'],
            'amount' => $data['amount'],
            'amount_repaid' => 0,
            'currency_code' => $data['currency_code'],
            'borrowed_date' => $data['borrowed_date'],
            'due_date' => $data['due_date'] ?? null,
            'message' => $data['message'] ?? null,
            'status' => 'active',
        ]);
    }

    public function updateBorrowing(Borrowing $borrowing, array $data): Borrowing
    {
        return DB::transaction(function () use ($borrowing, $data) {
            $locked = Borrowing::query()->lockForUpdate()->findOrFail($borrowing->id);
            $repaid = (float) $locked->repayments()->sum('amount');
            $amount = (float) ($data['amount'] ?? $locked->amount);

            if ($amount < $repaid) {
                throw ValidationException::withMessages([
                    'amount' => 'The amount cannot be less than the amount already repaid.',
                ]);
            }

            if (isset($data['currency_code'])
                && $data['currency_code'] !== $locked->currency_code
                && $repaid > 0) {
                throw ValidationException::withMessages([
                    'currency_code' => 'The currency cannot change after a repayment has been recorded.',
                ]);
            }

            $locked->update($data);
            $this->recalculateStatus($locked);

            return $locked->fresh();
        });
    }

    public function addRepayment(Borrowing $borrowing, array $data): BorrowingRepayment
    {
        return DB::transaction(function () use ($borrowing, $data) {
            $locked = Borrowing::query()->lockForUpdate()->findOrFail($borrowing->id);
            $amount = abs((float) $data['amount']);
            $repaid = (float) $locked->repayments()->sum('amount');
            $remaining = max((float) $locked->amount - $repaid, 0);

            if (! in_array($locked->status, ['active', 'partially_repaid'], true)) {
                throw ValidationException::withMessages([
                    'amount' => 'Repayments can only be added to an outstanding borrowing.',
                ]);
            }

            if ($amount <= 0 || $amount > $remaining) {
                throw ValidationException::withMessages([
                    'amount' => 'The repayment cannot exceed the remaining principal.',
                ]);
            }

            $repayment = $locked->repayments()->create([
                'amount' => $amount,
                'repaid_at' => $data['repaid_at'] ?? now()->toDateString(),
                'note' => $data['note'] ?? null,
            ]);

            $this->recalculateStatus($locked);

            return $repayment;
        });
    }

    public function deleteRepayment(BorrowingRepayment $repayment): void
    {
        DB::transaction(function () use ($repayment) {
            $borrowing = Borrowing::query()->lockForUpdate()->findOrFail($repayment->borrowing_id);
            // Undo the account movement recorded together with this repayment
            if ($repayment->transaction_id && $transaction = Transaction::find($repayment->transaction_id)) {
                $this->transactions->reverseAndDelete($transaction);
            }

            $repayment->forceDelete();
            $this->recalculateStatus($borrowing);
        });
    }

    public function recalculateStatus(Borrowing $borrowing): void
    {
        $borrowing->refresh();
        $repaid = (float) $borrowing->repayments()->sum('amount');

        $status = match (true) {
            $borrowing->status === 'written_off' => 'written_off',
            $repaid >= $borrowing->amount && $borrowing->amount > 0 => 'repaid',
            $repaid > 0 => 'partially_repaid',
            default => 'active',
        };

        $borrowing->update([
            'amount_repaid' => $repaid,
            'status' => $status,
        ]);
    }
}
