<?php

namespace Modules\Finance\Http\Requests\Borrowing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Finance\Models\Transaction;

class StoreBorrowingRequest extends FormRequest
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
                ->where('transaction_type', 'income')
                ->whereNull('transfer_transaction_id'))],
            'lender_name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency_code' => ['required', 'string', 'size:3', 'exists:currencies,code'],
            'borrowed_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:borrowed_date'],
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
                    ->where('transaction_type', 'income')
                    ->whereNull('transfer_transaction_id')
                    ->value('currency_code');
                $borrowingCurrency = $this->input('currency_code', $this->route('borrowing')?->currency_code);
                if ($currency !== null && $currency !== $borrowingCurrency) {
                    $validator->errors()->add('transaction_id', 'The linked transaction must use the borrowing currency.');
                }
            }

            $borrowing = $this->route('borrowing');
            if ($borrowing && $this->filled('amount') && (float) $this->input('amount') < (float) $borrowing->amount_repaid) {
                $validator->errors()->add('amount', 'The amount cannot be less than the amount already repaid.');
            }
        }];
    }
}
