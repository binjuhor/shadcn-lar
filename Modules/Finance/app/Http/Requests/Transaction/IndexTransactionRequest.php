<?php

namespace Modules\Finance\Http\Requests\Transaction;

use Illuminate\Foundation\Http\FormRequest;

class IndexTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'account_ids' => ['nullable', 'array'],
            'account_ids.*' => ['exists:finance_accounts,id'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['exists:finance_categories,id'],
            'type' => ['nullable', 'in:income,expense,transfer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'amount_from' => ['nullable', 'numeric', 'min:0'],
            'amount_to' => ['nullable', 'numeric', 'min:0', 'gte:amount_from'],
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function filters(): array
    {
        return $this->only(['account_ids', 'category_ids', 'type', 'date_from', 'date_to', 'amount_from', 'amount_to', 'search']);
    }
}
