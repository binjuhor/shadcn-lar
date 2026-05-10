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
            'transaction_type' => ['sometimes', 'in:income,expense'],
            'account_id' => ['sometimes', 'exists:finance_accounts,id'],
            'category_id' => ['nullable', 'exists:finance_categories,id'],
            'amount' => ['sometimes', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'transaction_date' => ['sometimes', 'date'],
            'bills' => ['nullable', 'array', 'max:10'],
            // extensions: rule checks the user-supplied extension and is more reliable than mimes:
            // for HEIC, where the server may report image/heic as application/octet-stream.
            'bills.*' => ['file', 'extensions:jpg,jpeg,png,webp,heic,heif,pdf', 'max:10240'],
            'removed_bill_ids' => ['nullable', 'array'],
            'removed_bill_ids.*' => ['integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.min' => 'Amount must be positive',
        ];
    }
}
