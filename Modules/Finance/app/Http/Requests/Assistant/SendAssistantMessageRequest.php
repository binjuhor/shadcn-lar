<?php

namespace Modules\Finance\Http\Requests\Assistant;

use Illuminate\Foundation\Http\FormRequest;

class SendAssistantMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => ['nullable', 'string', 'max:2000', 'required_without_all:image,audio'],
            // 'image' rule rejects HEIC (image/heic or application/octet-stream on servers without libheif)
            'image' => ['nullable', 'file', 'extensions:jpg,jpeg,png,gif,bmp,webp,heic,heif', 'max:10240', 'prohibits:audio'],
            'audio' => ['nullable', 'file', 'max:10240', 'mimes:webm,ogg,mp3,wav,aac,flac,aiff,mp4,m4a'],
            'conversation_id' => ['nullable', 'integer'],
            'language' => ['nullable', 'string', 'in:vi,en'],
        ];
    }
}
