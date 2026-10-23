<?php

namespace Modules\Finance\Http\Requests\Loan;

use Illuminate\Support\Carbon;
use Modules\Finance\Models\Transaction;

class UpdateLoanRequest extends StoreLoanRequest
{
    public function rules(): array
    {
        $rules = parent::rules();

        foreach (['borrower_name', 'amount', 'currency_code', 'loan_date'] as $field) {
            $rules[$field][0] = 'sometimes';
        }

        $rules['status'] = ['sometimes', 'in:active,written_off'];

        return $rules;
    }

    public function after(): array
    {
        return [function ($validator): void {
            $loan = $this->route('loan');
            if (! $loan) {
                return;
            }

            $currencyCode = $this->input('currency_code', $loan->currency_code);
            $transactionId = $this->exists('transaction_id')
                ? $this->input('transaction_id')
                : $loan->transaction_id;

            if ($transactionId) {
                $transactionCurrency = Transaction::query()
                    ->whereKey($transactionId)
                    ->where('user_id', $this->user()->id)
                    ->where('transaction_type', 'expense')
                    ->whereNull('transfer_transaction_id')
                    ->value('currency_code');

                if ($transactionCurrency !== null && $transactionCurrency !== $currencyCode) {
                    $validator->errors()->add('transaction_id', 'The linked transaction must use the loan currency.');
                }
            }

            if ($this->filled('currency_code')
                && $currencyCode !== $loan->currency_code
                && $loan->repayments()->exists()) {
                $validator->errors()->add('currency_code', 'The currency cannot change after a repayment has been recorded.');
            }

            if ($this->filled('amount') && (float) $this->input('amount') < (float) $loan->amount_repaid) {
                $validator->errors()->add('amount', 'The amount cannot be less than the amount already repaid.');
            }

            if ($validator->errors()->has('loan_date') || $validator->errors()->has('due_date')) {
                return;
            }

            $loanDate = Carbon::parse($this->input('loan_date', $loan->loan_date));
            $dueDate = $this->exists('due_date') ? $this->input('due_date') : $loan->due_date;
            if ($dueDate && Carbon::parse($dueDate)->lt($loanDate)) {
                $validator->errors()->add('due_date', 'The due date must be on or after the loan date.');
            }
        }];
    }
}
