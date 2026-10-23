<?php

namespace Modules\Finance\Http\Requests\Borrowing;

use Illuminate\Foundation\Http\FormRequest;

class StoreBorrowingRepaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('borrowing'));
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.$this->route('borrowing')->remaining_amount],
            'repaid_at' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
