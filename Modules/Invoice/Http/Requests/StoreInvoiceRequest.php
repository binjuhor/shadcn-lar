<?php

namespace Modules\Invoice\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'invoice_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:invoice_date'],
            'from_name' => ['required', 'string', 'max:255'],
            'from_address' => ['nullable', 'string', 'max:1000'],
            'from_email' => ['nullable', 'email', 'max:255'],
            'from_phone' => ['nullable', 'string', 'max:50'],
            'to_name' => ['required', 'string', 'max:255'],
            'to_address' => ['nullable', 'string', 'max:1000'],
            'to_email' => ['nullable', 'email', 'max:255'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:1'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
