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
            'currency_code' => ['sometimes', 'string', 'size:3', 'exists:currencies,code'],
            'rate_source' => ['nullable', 'string'],
            'initial_balance' => ['sometimes', 'numeric', 'between:-999999999999999999,999999999999999999'],
            'description' => ['nullable', 'string', 'max:1000'],
            'color' => ['nullable', 'string', 'max:7'],
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
