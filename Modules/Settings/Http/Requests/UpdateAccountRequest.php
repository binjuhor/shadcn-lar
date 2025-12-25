<?php

namespace Modules\Settings\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:30'],
            'dob' => ['nullable', 'date'],
            'language' => ['required', 'string', 'in:en,fr,de,es,pt,ru,ja,ko,zh'],
        ];
    }
}
