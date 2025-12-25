<?php

namespace Modules\Settings\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => [
                'required',
                'string',
                'min:2',
                'max:30',
                Rule::unique('users')->ignore($this->user()->id),
            ],
            'email' => [
                'required',
                'email',
                Rule::unique('users')->ignore($this->user()->id),
            ],
            'bio' => ['nullable', 'string', 'max:160'],
            'urls' => ['nullable', 'array'],
            'urls.*.value' => ['required_with:urls', 'url'],
        ];
    }
}
