<?php

namespace Modules\AiAdvisor\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\AiAdvisor\DTOs\{ToolCall, ToolContext};
use Modules\AiAdvisor\Enums\ToolRiskLevel;
use Modules\AiAdvisor\Services\FinanceAdvisorRuntime;
use Modules\AiAdvisor\Services\{ToolExecutor, ToolRegistry};
use Modules\AiAdvisor\Tools\Finance\FinanceToolCatalog;
use Modules\Finance\Models\{Account, Currency};
use Modules\Finance\Models\Transaction;
use Tests\TestCase;

class FinanceToolCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_advisor_and_assistant_tools_are_registered_with_valid_definitions(): void
    {
        $registry = app(ToolRegistry::class);

        // 32 advisor catalog tools + the assistant-only draftTransactions tool
        $this->assertCount(32, FinanceToolCatalog::classes());
        $this->assertCount(33, $registry->all());
        $this->assertArrayHasKey('draftTransactions', $registry->all());

        foreach ($registry->definitions() as $definition) {
            $this->assertSame('object', $definition['input_schema']['type']);
            $this->assertNotEmpty($definition['description']);
            $properties = $definition['input_schema']['properties'] ?? [];
            $this->assertArrayNotHasKey('_confirmed', $properties);
            $this->assertArrayNotHasKey('_confirmation_token', $properties);
        }
    }

    public function test_read_tool_is_scoped_to_the_context_actor(): void
    {
        $this->createCurrency();
        $actor = User::factory()->create();
        $other = User::factory()->create();
        Account::create($this->accountData($actor->id, 'Visible Account'));
        Account::create($this->accountData($other->id, 'Hidden Account'));

        $result = app(ToolExecutor::class)->execute(
            new ToolContext($actor, (string) Str::uuid()),
            new ToolCall('call-1', 'getAccounts', []),
        );

        $this->assertTrue($result->success);
        $this->assertSame(['Visible Account'], array_column($result->data['items'], 'name'));
    }

    public function test_write_tools_are_disabled_by_default(): void
    {
        $this->createCurrency();
        $actor = User::factory()->create();
        $account = Account::create($this->accountData($actor->id, 'Cash'));

        $result = app(ToolExecutor::class)->execute(
            new ToolContext($actor, (string) Str::uuid()),
            new ToolCall('call-2', 'createTransaction', [
                'account_id' => $account->id,
                'transaction_type' => 'expense',
                'amount' => 100,
                'transaction_date' => now()->toDateString(),
            ]),
        );

        $this->assertFalse($result->success);
        $this->assertSame('mutations_disabled', $result->errorCode);
    }

    public function test_catalog_has_twenty_read_and_thirteen_write_tools(): void
    {
        $tools = collect(app(ToolRegistry::class)->all())->except('draftTransactions');

        $this->assertCount(19, $tools->filter(fn ($tool) => $tool->riskLevel() === ToolRiskLevel::Read));
        $this->assertCount(13, $tools->filter(fn ($tool) => $tool->riskLevel() === ToolRiskLevel::Write));
    }

    public function test_confirmed_write_is_idempotent(): void
    {
        config(['aiadvisor.mutations_enabled' => true]);
        $this->createCurrency();
        $actor = User::factory()->create();
        $account = Account::create($this->accountData($actor->id, 'Cash'));
        $context = new ToolContext($actor, (string) Str::uuid(), 10);
        $arguments = [
            'account_id' => $account->id,
            'transaction_type' => 'expense',
            'amount' => 100,
            'transaction_date' => now()->toDateString(),
        ];
        $executor = app(ToolExecutor::class);
        $preview = $executor->execute($context, new ToolCall('call-3', 'createTransaction', $arguments));
        $token = $preview->meta['confirmation_token'];

        $confirmedCall = new ToolCall('call-4', 'createTransaction', $arguments, true, $token, 'expense-100');
        $first = $executor->execute($context, $confirmedCall);
        $second = $executor->execute($context, $confirmedCall);

        $this->assertTrue($first->success);
        $this->assertTrue($second->success);
        $this->assertSame($first->data['id'], $second->data['id']);
        $this->assertSame(1, Transaction::where('user_id', $actor->id)->count());
        $this->assertSame(900.0, (float) $account->fresh()->current_balance);
    }

    public function test_application_runtime_can_confirm_a_pending_write(): void
    {
        config(['aiadvisor.mutations_enabled' => true]);
        $this->createCurrency();
        $actor = User::factory()->create();
        $account = Account::create($this->accountData($actor->id, 'Cash'));
        $context = new ToolContext($actor, (string) Str::uuid(), 42);
        $arguments = [
            'account_id' => $account->id,
            'transaction_type' => 'expense',
            'amount' => 75,
            'transaction_date' => now()->toDateString(),
        ];
        $preview = app(ToolExecutor::class)->execute($context, new ToolCall('preview', 'createTransaction', $arguments));

        $response = app(FinanceAdvisorRuntime::class)->confirm($actor, 42, $preview->meta['confirmation_token']);

        $this->assertSame('The financial change was completed successfully.', $response['content']);
        $this->assertSame(1, Transaction::where('user_id', $actor->id)->count());
        $this->assertSame(925.0, (float) $account->fresh()->current_balance);
    }

    /** @return array<string, mixed> */
    private function accountData(int $userId, string $name): array
    {
        return [
            'user_id' => $userId,
            'name' => $name,
            'account_type' => 'cash',
            'currency_code' => 'USD',
            'initial_balance' => 1000,
            'current_balance' => 1000,
            'is_active' => true,
            'exclude_from_total' => false,
        ];
    }

    private function createCurrency(): void
    {
        Currency::create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'decimal_places' => 2,
            'is_active' => true,
        ]);
    }
}
