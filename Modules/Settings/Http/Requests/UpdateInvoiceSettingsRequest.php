<?php

namespace Modules\Settings\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'default_currency' => ['required', 'string', 'size:3'],
            'default_tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'default_payment_terms' => ['required', 'integer', 'min:0', 'max:365'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'company_address' => ['nullable', 'string', 'max:1000'],
            'company_email' => ['nullable', 'email', 'max:255'],
            'company_phone' => ['nullable', 'string', 'max:50'],
        ];
    }
}
