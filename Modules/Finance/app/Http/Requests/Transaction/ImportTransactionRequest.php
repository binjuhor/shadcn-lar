<?php

namespace Modules\Finance\Http\Requests\Transaction;

use Illuminate\Foundation\Http\FormRequest;

class ImportTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls,pdf', 'max:10240'], // 10MB max
            'account_id' => ['required', 'exists:finance_accounts,id'],
            'source' => ['required', 'in:payoneer,techcombank,techcombank_pdf,generic'],
            'skip_duplicates' => ['boolean'],
            'category_mappings' => ['array'],
            'category_mappings.*' => ['nullable', 'exists:finance_categories,id'],
        ];
    }
}
