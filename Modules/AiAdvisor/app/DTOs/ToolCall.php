<?php

namespace Modules\AiAdvisor\DTOs;

final readonly class ToolCall
{
    /** @param array<string, mixed> $arguments */
    public function __construct(
        public string $id,
        public string $name,
        public array $arguments,
        public bool $confirmed = false,
        public ?string $confirmationToken = null,
        public ?string $idempotencyKey = null,
    ) {}
}
