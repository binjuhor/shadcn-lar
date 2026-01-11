<?php

namespace Modules\Finance\Http\Requests\Transaction;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $transaction = $this->route('transaction');

        return auth()->check() && $transaction->account->user_id === auth()->id();
    }

    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'exists:finance_categories,id'],
            'amount' => ['sometimes', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'transaction_date' => ['sometimes', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.min' => 'Amount must be positive',
        ];
    }
}
