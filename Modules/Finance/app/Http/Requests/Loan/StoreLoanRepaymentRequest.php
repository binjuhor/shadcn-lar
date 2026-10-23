<?php

namespace Modules\Finance\Http\Requests\Loan;

use Illuminate\Foundation\Http\FormRequest;

class StoreLoanRepaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('loan'));
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.$this->route('loan')->remaining_amount],
            'repaid_at' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
