<?php

namespace Modules\Finance\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Finance\Models\{Budget, Category, Currency};
use Tests\TestCase;

class BudgetApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        Currency::create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'decimal_places' => 2,
            'is_default' => true,
        ]);

        $this->category = Category::create([
            'name' => 'Food & Dining',
            'type' => 'expense',
            'color' => '#ff0000',
        ]);
    }

    protected function createBudget(array $attributes = []): Budget
    {
        return Budget::create(array_merge([
            'user_id' => $this->user->id,
            'name' => 'Food Budget',
            'category_id' => $this->category->id,
            'allocated_amount' => 500,
            'spent_amount' => 0,
            'currency_code' => 'USD',
            'period_type' => 'monthly',
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->endOfMonth(),
            'is_active' => true,
        ], $attributes));
    }

    public function test_user_can_list_budgets(): void
    {
        Sanctum::actingAs($this->user);

        $this->createBudget(['name' => 'Budget 1']);
        $this->createBudget(['name' => 'Budget 2']);

        $response = $this->getJson('/api/v1/finance/budgets');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_user_can_create_budget(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/finance/budgets', [
            'name' => 'Entertainment Budget',
            'category_id' => $this->category->id,
            'allocated_amount' => 300,
            'currency_code' => 'USD',
            'period_type' => 'monthly',
            'start_date' => now()->startOfMonth()->format('Y-m-d'),
            'end_date' => now()->endOfMonth()->format('Y-m-d'),
            'is_active' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Entertainment Budget');
    }

    public function test_user_can_view_budget(): void
    {
        Sanctum::actingAs($this->user);

        $budget = $this->createBudget();

        $response = $this->getJson("/api/v1/finance/budgets/{$budget->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $budget->id);
    }

    public function test_user_can_update_budget(): void
    {
        Sanctum::actingAs($this->user);

        $budget = $this->createBudget();

        $response = $this->putJson("/api/v1/finance/budgets/{$budget->id}", [
            'name' => 'Updated Budget',
            'allocated_amount' => 600,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Budget')
            ->assertJsonPath('data.allocated_amount', 600);
    }

    public function test_user_can_delete_budget(): void
    {
        Sanctum::actingAs($this->user);

        $budget = $this->createBudget();

        $response = $this->deleteJson("/api/v1/finance/budgets/{$budget->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('finance_budgets', ['id' => $budget->id]);
    }

    public function test_user_can_refresh_budget(): void
    {
        Sanctum::actingAs($this->user);

        $budget = $this->createBudget();

        $response = $this->postJson("/api/v1/finance/budgets/{$budget->id}/refresh");

        $response->assertOk();
    }

    public function test_user_can_get_budget_summary(): void
    {
        Sanctum::actingAs($this->user);

        $this->createBudget(['allocated_amount' => 500, 'spent_amount' => 200]);
        $this->createBudget(['allocated_amount' => 300, 'spent_amount' => 100]);

        $response = $this->getJson('/api/v1/finance/budgets-summary');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'total_allocated',
                    'total_spent',
                    'total_remaining',
                    'active_budgets_count',
                    'over_budget_count',
                ],
            ]);
    }

    public function test_user_cannot_access_other_users_budget(): void
    {
        Sanctum::actingAs($this->user);

        $otherUser = User::factory()->create();
        $budget = Budget::create([
            'user_id' => $otherUser->id,
            'name' => 'Other Budget',
            'category_id' => $this->category->id,
            'allocated_amount' => 500,
            'spent_amount' => 0,
            'currency_code' => 'USD',
            'period_type' => 'monthly',
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->endOfMonth(),
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/v1/finance/budgets/{$budget->id}");

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_access_budgets(): void
    {
        $response = $this->getJson('/api/v1/finance/budgets');

        $response->assertUnauthorized();
    }
}
