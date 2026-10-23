<?php

namespace Modules\AiAdvisor\Services;

use Modules\AiAdvisor\Contracts\AgentTool;
use Modules\AiAdvisor\Exceptions\UnknownToolException;

class ToolRegistry
{
    /** @var array<string, AgentTool> */
    private array $tools = [];

    public function register(AgentTool $tool): void
    {
        if (isset($this->tools[$tool->name()])) {
            throw new \LogicException("Duplicate AI tool name: {$tool->name()}");
        }

        $this->tools[$tool->name()] = $tool;
    }

    public function get(string $name): AgentTool
    {
        return $this->tools[$name] ?? throw new UnknownToolException("Unknown AI tool: {$name}");
    }

    /** @return array<string, AgentTool> */
    public function all(): array
    {
        return $this->tools;
    }

    /** @return array<int, array<string, mixed>> */
    public function definitions(?array $allowed = null): array
    {
        return collect($this->tools)
            ->when($allowed !== null, fn ($tools) => $tools->only($allowed))
            ->map(fn (AgentTool $tool) => [
                'name' => $tool->name(),
                'description' => $tool->description(),
                'input_schema' => $tool->inputSchema(),
            ])
            ->values()
            ->all();
    }
}
