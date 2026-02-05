<?php

namespace Modules\Settings\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFinanceSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'default_currency' => ['required', 'string', 'size:3', 'exists:currencies,code'],
            'default_exchange_rate_source' => ['nullable', 'string'],
            'fiscal_year_start' => ['required', 'integer', 'min:1', 'max:12'],
            'number_format' => ['required', 'string', 'in:thousand_comma,thousand_dot,space_dot,space_comma'],
            'default_smart_input_account_id' => ['nullable', 'integer', 'exists:finance_accounts,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->default_exchange_rate_source === '__default__' || $this->default_exchange_rate_source === '') {
            $this->merge(['default_exchange_rate_source' => null]);
        }
    }
}
