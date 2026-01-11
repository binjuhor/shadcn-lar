<?php

namespace Modules\Finance\Http\Requests\Transaction;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Finance\Models\Account;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! auth()->check()) {
            return false;
        }

        $account = Account::find($this->account_id);

        return $account && $account->user_id === auth()->id();
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:income,expense,transfer'],
            'account_id' => ['required', 'exists:finance_accounts,id'],
            'category_id' => ['nullable', 'exists:finance_categories,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'transaction_date' => ['required', 'date'],
            'transfer_account_id' => ['nullable', 'exists:finance_accounts,id', 'different:account_id'],
        ];
    }

    public function messages(): array
    {
        return [
            'account_id.required' => 'Please select an account',
            'amount.required' => 'Amount is required',
            'amount.min' => 'Amount must be positive',
            'transfer_account_id.different' => 'Cannot transfer to the same account',
        ];
    }
}
