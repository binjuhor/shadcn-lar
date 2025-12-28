<?php

namespace Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'account_id' => ['required', 'exists:finance_accounts,id'],
            'transaction_type' => ['required', 'in:income,expense,transfer'],
            'amount' => ['required', 'integer', 'min:1'],
            'category_id' => ['nullable', 'exists:finance_categories,id'],
            'description' => ['nullable', 'string', 'max:500'],
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],

            'from_account_id' => ['required_if:transaction_type,transfer', 'exists:finance_accounts,id'],
            'to_account_id' => ['required_if:transaction_type,transfer', 'exists:finance_accounts,id', 'different:from_account_id'],
        ];
    }

    public function messages(): array
    {
        return [
            'account_id.required' => 'Please select an account',
            'amount.required' => 'Amount is required',
            'amount.integer' => 'Amount must be in cents',
            'amount.min' => 'Amount must be positive',
            'transaction_date.before_or_equal' => 'Cannot record future transactions',
            'to_account_id.different' => 'Cannot transfer to the same account',
        ];
    }
}
