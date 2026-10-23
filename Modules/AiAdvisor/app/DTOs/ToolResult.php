<?php

namespace Modules\AiAdvisor\DTOs;

final readonly class ToolResult
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public bool $success,
        public array $data = [],
        public ?string $errorCode = null,
        public ?string $message = null,
        public array $meta = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function success(array $data, array $meta = []): self
    {
        return new self(true, $data, meta: $meta);
    }

    public static function error(string $code, string $message, array $meta = []): self
    {
        return new self(false, errorCode: $code, message: $message, meta: $meta);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'success' => $this->success,
            'data' => $this->data,
            'error_code' => $this->errorCode,
            'message' => $this->message,
            'meta' => $this->meta,
        ], fn (mixed $value) => $value !== null && $value !== []);
    }
}
