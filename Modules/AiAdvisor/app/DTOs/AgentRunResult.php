<?php

namespace Modules\AiAdvisor\DTOs;

final readonly class AgentRunResult
{
    /**
     * @param  array<string, mixed>  $usage
     * @param  list<array<string, mixed>>  $drafts  editable transaction drafts produced by tools
     * @param  list<array<string, mixed>>  $actions  client actions, e.g. a pending confirmation
     */
    public function __construct(
        public string $content,
        public array $usage = [],
        public int $toolRounds = 0,
        public array $drafts = [],
        public array $actions = [],
        public ?string $provider = null,
    ) {}
}
