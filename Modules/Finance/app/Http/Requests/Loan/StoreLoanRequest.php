<?php

namespace Modules\Finance\Http\Requests\Loan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Finance\Models\Transaction;

class StoreLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'transaction_id' => ['nullable', 'integer', Rule::exists('finance_transactions', 'id')->where(fn ($query) => $query
                ->where('user_id', $this->user()->id)
                ->where('transaction_type', 'expense')
                ->whereNull('transfer_transaction_id'))],
            'borrower_name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency_code' => ['required', 'string', 'size:3', 'exists:currencies,code'],
            'loan_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:loan_date'],
            'message' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            if ($this->filled('transaction_id')) {
                $currency = Transaction::query()
                    ->whereKey($this->integer('transaction_id'))
                    ->where('user_id', $this->user()->id)
                    ->where('transaction_type', 'expense')
                    ->whereNull('transfer_transaction_id')
                    ->value('currency_code');
                $loanCurrency = $this->input('currency_code', $this->route('loan')?->currency_code);
                if ($currency !== null && $currency !== $loanCurrency) {
                    $validator->errors()->add('transaction_id', 'The linked transaction must use the loan currency.');
                }
            }

            $loan = $this->route('loan');
            if ($loan && $this->filled('amount') && (float) $this->input('amount') < (float) $loan->amount_repaid) {
                $validator->errors()->add('amount', 'The amount cannot be less than the amount already repaid.');
            }
        }];
    }
}
