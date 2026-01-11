<?php

namespace Modules\Finance\Http\Requests\Transaction;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Finance\Models\Account;

class ConversionPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! auth()->check()) {
            return false;
        }

        $userId = auth()->id();
        $fromAccount = Account::find($this->from_account_id);
        $toAccount = Account::find($this->to_account_id);

        return $fromAccount?->user_id === $userId && $toAccount?->user_id === $userId;
    }

    public function rules(): array
    {
        return [
            'from_account_id' => ['required', 'exists:finance_accounts,id'],
            'to_account_id' => ['required', 'exists:finance_accounts,id'],
            'amount' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'from_account_id.required' => 'Source account is required',
            'to_account_id.required' => 'Destination account is required',
        ];
    }
}
