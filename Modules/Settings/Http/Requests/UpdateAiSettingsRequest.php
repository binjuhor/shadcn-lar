<?php

namespace Modules\Settings\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Settings\Rules\AllowedOllamaOrigin;
use Modules\Settings\Services\AiCredentialResolver;

class UpdateAiSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'provider' => ['sometimes', 'string', Rule::in(AiCredentialResolver::PROVIDERS)],
            'ollama_base_url' => ['nullable', 'url:http,https', 'max:2048', new AllowedOllamaOrigin],
            'ollama_model' => ['nullable', 'string', 'max:255'],
            'keys' => ['sometimes', 'array:'.implode(',', AiCredentialResolver::PROVIDERS)],
            'keys.*' => ['nullable', 'string', 'max:4096'],
            'clear' => ['sometimes', 'array'],
            'clear.*' => ['string', Rule::in(AiCredentialResolver::PROVIDERS)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $keys = collect($this->input('keys', []))
            ->map(fn (mixed $key) => is_string($key) ? trim($key) : $key)
            ->all();

        $this->merge(['keys' => $keys]);

        $baseUrl = trim((string) $this->input('ollama_base_url', ''));
        $this->merge(['ollama_base_url' => $baseUrl === '' ? null : rtrim($baseUrl, '/')]);

        $model = trim((string) $this->input('ollama_model', ''));
        $this->merge(['ollama_model' => $model === '' ? null : $model]);
    }
}
