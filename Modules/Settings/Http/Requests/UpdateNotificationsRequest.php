<?php

namespace Modules\Settings\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:all,mentions,none'],
            'mobile' => ['nullable', 'boolean'],
            'communication_emails' => ['nullable', 'boolean'],
            'social_emails' => ['nullable', 'boolean'],
            'marketing_emails' => ['nullable', 'boolean'],
            'security_emails' => ['required', 'boolean'],
        ];
    }
}
