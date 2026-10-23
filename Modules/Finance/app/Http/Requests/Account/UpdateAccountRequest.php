<?php

namespace Modules\Finance\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        $account = $this->route('account');

        return auth()->check() && $account->user_id === auth()->id();
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'account_type' => ['sometimes', 'in:bank,credit_card,investment,cash,loan,e_wallet,other'],
            'has_credit_limit' => ['sometimes', 'boolean'],
            'currency_code' => ['sometimes', 'string', 'size:3', 'exists:currencies,code'],
            'account_number' => ['nullable', 'string', 'max:50'],
            'institution_name' => ['nullable', 'string', 'max:255'],
            'rate_source' => ['nullable', 'string'],
            'initial_balance' => ['sometimes', 'numeric', 'between:-999999999999999999,999999999999999999'],
            'current_balance' => ['sometimes', 'numeric'],
            'description' => ['nullable', 'string', 'max:1000'],
            'color' => ['nullable', 'string', 'max:7'],
            'icon' => ['nullable', 'file', 'extensions:svg,png,jpg,jpeg,webp', 'max:512'],
            'bank_icon' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'is_default_payment' => ['sometimes', 'boolean'],
            'exclude_from_total' => ['sometimes', 'boolean'],
        ];
    }

    public function prepareForValidation(): void
    {
        if ($this->has('rate_source')) {
            if ($this->rate_source === '__default__' || $this->rate_source === '') {
                $this->merge(['rate_source' => null]);
            }
        }
    }
}
