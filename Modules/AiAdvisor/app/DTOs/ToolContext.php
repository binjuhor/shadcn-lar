<?php

namespace Modules\AiAdvisor\DTOs;

use App\Models\User;

final readonly class ToolContext
{
    public function __construct(
        public User $actor,
        public string $correlationId,
        public ?int $conversationId = null,
        public ?string $locale = null,
        public ?string $currency = null,
    ) {}
}
