<?php

namespace Modules\Finance\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'account_type' => ['required', 'in:bank,credit_card,investment,cash,loan,e_wallet,other'],
            'has_credit_limit' => ['boolean'],
            'currency_code' => ['required', 'string', 'size:3', 'exists:currencies,code'],
            'rate_source' => ['nullable', 'string'],
            'initial_balance' => ['required', 'numeric', 'between:-999999999999999999,999999999999999999'],
            'description' => ['nullable', 'string', 'max:1000'],
            'color' => ['nullable', 'string', 'max:7'],
            'is_active' => ['boolean'],
            'is_default_payment' => ['boolean'],
            'exclude_from_total' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Account name is required',
            'account_type.required' => 'Please select an account type',
            'currency_code.required' => 'Please select a currency',
            'initial_balance.required' => 'Initial balance is required',
        ];
    }

    public function prepareForValidation(): void
    {
        if ($this->rate_source === '__default__' || $this->rate_source === '') {
            $this->merge(['rate_source' => null]);
        }
    }
}
