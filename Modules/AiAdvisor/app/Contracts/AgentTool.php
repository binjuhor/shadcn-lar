<?php

namespace Modules\AiAdvisor\Contracts;

use Modules\AiAdvisor\DTOs\{ToolContext, ToolResult};
use Modules\AiAdvisor\Enums\ToolRiskLevel;

interface AgentTool
{
    public function name(): string;

    public function description(): string;

    /** @return array<string, mixed> */
    public function inputSchema(): array;

    /** @return array<string, mixed> */
    public function rules(): array;

    public function riskLevel(): ToolRiskLevel;

    /** @param array<string, mixed> $arguments */
    public function execute(ToolContext $context, array $arguments): ToolResult;
}
