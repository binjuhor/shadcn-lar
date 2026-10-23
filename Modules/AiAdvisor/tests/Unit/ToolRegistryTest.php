<?php

namespace Modules\AiAdvisor\Tests\Unit;

use Modules\AiAdvisor\Contracts\AgentTool;
use Modules\AiAdvisor\DTOs\{ToolContext, ToolResult};
use Modules\AiAdvisor\Enums\ToolRiskLevel;
use Modules\AiAdvisor\Exceptions\UnknownToolException;
use Modules\AiAdvisor\Services\ToolRegistry;
use PHPUnit\Framework\TestCase;

class ToolRegistryTest extends TestCase
{
    public function test_it_registers_and_describes_a_tool(): void
    {
        $registry = new ToolRegistry;
        $tool = $this->tool('example');
        $registry->register($tool);

        $this->assertSame($tool, $registry->get('example'));
        $this->assertSame('example', $registry->definitions()[0]['name']);
    }

    public function test_it_rejects_duplicate_and_unknown_tools(): void
    {
        $registry = new ToolRegistry;
        $registry->register($this->tool('duplicate'));

        $this->expectException(\LogicException::class);
        $registry->register($this->tool('duplicate'));
    }

    public function test_unknown_tool_throws_a_specific_exception(): void
    {
        $this->expectException(UnknownToolException::class);
        (new ToolRegistry)->get('missing');
    }

    private function tool(string $name): AgentTool
    {
        return new class($name) implements AgentTool
        {
            public function __construct(private string $toolName) {}

            public function name(): string
            {
                return $this->toolName;
            }

            public function description(): string
            {
                return 'Example';
            }

            public function inputSchema(): array
            {
                return ['type' => 'object'];
            }

            public function rules(): array
            {
                return [];
            }

            public function riskLevel(): ToolRiskLevel
            {
                return ToolRiskLevel::Read;
            }

            public function execute(ToolContext $context, array $arguments): ToolResult
            {
                return ToolResult::success([]);
            }
        };
    }
}
