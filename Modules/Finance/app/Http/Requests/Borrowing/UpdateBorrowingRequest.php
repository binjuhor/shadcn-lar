<?php

namespace Modules\Finance\Http\Requests\Borrowing;

use Illuminate\Support\Carbon;
use Modules\Finance\Models\Transaction;

class UpdateBorrowingRequest extends StoreBorrowingRequest
{
    public function rules(): array
    {
        $rules = parent::rules();

        foreach (['lender_name', 'amount', 'currency_code', 'borrowed_date'] as $field) {
            $rules[$field][0] = 'sometimes';
        }

        $rules['status'] = ['sometimes', 'in:active,written_off'];

        return $rules;
    }

    public function after(): array
    {
        return [function ($validator): void {
            $borrowing = $this->route('borrowing');
            if (! $borrowing) {
                return;
            }

            $currencyCode = $this->input('currency_code', $borrowing->currency_code);
            $transactionId = $this->exists('transaction_id')
                ? $this->input('transaction_id')
                : $borrowing->transaction_id;

            if ($transactionId) {
                $transactionCurrency = Transaction::query()
                    ->whereKey($transactionId)
                    ->where('user_id', $this->user()->id)
                    ->where('transaction_type', 'income')
                    ->whereNull('transfer_transaction_id')
                    ->value('currency_code');

                if ($transactionCurrency !== null && $transactionCurrency !== $currencyCode) {
                    $validator->errors()->add('transaction_id', 'The linked transaction must use the borrowing currency.');
                }
            }

            if ($this->filled('currency_code')
                && $currencyCode !== $borrowing->currency_code
                && $borrowing->repayments()->exists()) {
                $validator->errors()->add('currency_code', 'The currency cannot change after a repayment has been recorded.');
            }

            if ($this->filled('amount') && (float) $this->input('amount') < (float) $borrowing->amount_repaid) {
                $validator->errors()->add('amount', 'The amount cannot be less than the amount already repaid.');
            }

            if ($validator->errors()->has('borrowed_date') || $validator->errors()->has('due_date')) {
                return;
            }

            $borrowedDate = Carbon::parse($this->input('borrowed_date', $borrowing->borrowed_date));
            $dueDate = $this->exists('due_date') ? $this->input('due_date') : $borrowing->due_date;
            if ($dueDate && Carbon::parse($dueDate)->lt($borrowedDate)) {
                $validator->errors()->add('due_date', 'The due date must be on or after the borrowed date.');
            }
        }];
    }
}
