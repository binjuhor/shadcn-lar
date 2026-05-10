<?php

namespace Modules\Finance\Http\Requests\Transaction;

use Illuminate\Foundation\Http\FormRequest;

class ExportTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'format' => ['required', 'in:csv,excel'],
            'period' => ['required', 'in:custom,month,year'],
            'date_from' => ['required_if:period,custom', 'nullable', 'date'],
            'date_to' => ['required_if:period,custom', 'nullable', 'date', 'after_or_equal:date_from'],
            'month' => ['required_if:period,month', 'nullable', 'date_format:Y-m'],
            'year' => ['required_if:period,year', 'nullable', 'digits:4'],
            'account_ids' => ['nullable', 'array'],
            'account_ids.*' => ['exists:finance_accounts,id'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['exists:finance_categories,id'],
            'type' => ['nullable', 'in:income,expense,transfer'],
            'search' => ['nullable', 'string', 'max:255'],
            'amount_from' => ['nullable', 'numeric', 'min:0'],
            'amount_to' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'format.required' => 'Export format is required',
            'format.in' => 'Invalid export format',
            'period.required' => 'Period is required',
            'date_from.required_if' => 'Start date is required for custom period',
            'date_to.required_if' => 'End date is required for custom period',
        ];
    }
}
