<?php

namespace Modules\Finance\Http\Requests\Transaction;

use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'transaction_ids' => ['required', 'array', 'min:1'],
            'transaction_ids.*' => ['exists:finance_transactions,id'],
            'transaction_type' => ['nullable', 'in:income,expense'],
            'account_id' => ['nullable', 'exists:finance_accounts,id'],
            'category_id' => ['nullable', 'exists:finance_categories,id'],
            'transaction_date' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'transaction_ids.required' => 'Please select at least one transaction',
            'transaction_ids.min' => 'Please select at least one transaction',
        ];
    }
}
