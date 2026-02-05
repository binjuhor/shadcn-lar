<?php

namespace Modules\Finance\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Finance\Models\{Currency, FinancialPlan};
use Tests\TestCase;

class FinancialPlanApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

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
    }

    protected function createPlan(array $attributes = []): FinancialPlan
    {
        return FinancialPlan::create(array_merge([
            'user_id' => $this->user->id,
            'name' => 'Annual Budget 2026',
            'description' => 'Financial plan for 2026',
            'start_year' => 2026,
            'end_year' => 2026,
            'currency_code' => 'USD',
            'status' => 'draft',
        ], $attributes));
    }

    public function test_user_can_list_plans(): void
    {
        Sanctum::actingAs($this->user);

        $this->createPlan(['name' => 'Plan 1']);
        $this->createPlan(['name' => 'Plan 2']);

        $response = $this->getJson('/api/v1/finance/plans');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_user_can_create_plan(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/finance/plans', [
            'name' => 'New Financial Plan',
            'description' => 'My financial plan',
            'start_year' => 2026,
            'end_year' => 2026,
            'currency_code' => 'USD',
            'status' => 'draft',
            'periods' => [
                [
                    'year' => 2026,
                    'items' => [
                        [
                            'name' => 'Salary',
                            'type' => 'income',
                            'planned_amount' => 60000,
                            'recurrence' => 'monthly',
                        ],
                        [
                            'name' => 'Rent',
                            'type' => 'expense',
                            'planned_amount' => 18000,
                            'recurrence' => 'monthly',
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'New Financial Plan');
    }

    public function test_user_can_view_plan(): void
    {
        Sanctum::actingAs($this->user);

        $plan = $this->createPlan();

        $response = $this->getJson("/api/v1/finance/plans/{$plan->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $plan->id);
    }

    public function test_user_can_update_plan(): void
    {
        Sanctum::actingAs($this->user);

        $plan = $this->createPlan();
        $plan->periods()->create([
            'year' => 2026,
            'planned_income' => 0,
            'planned_expense' => 0,
        ]);

        $response = $this->putJson("/api/v1/finance/plans/{$plan->id}", [
            'name' => 'Updated Plan Name',
            'status' => 'active',
            'periods' => [
                [
                    'id' => $plan->periods->first()->id,
                    'year' => 2026,
                    'items' => [],
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Plan Name')
            ->assertJsonPath('data.status', 'active');
    }

    public function test_user_can_delete_plan(): void
    {
        Sanctum::actingAs($this->user);

        $plan = $this->createPlan();

        $response = $this->deleteJson("/api/v1/finance/plans/{$plan->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('finance_plans', ['id' => $plan->id]);
    }

    public function test_user_can_compare_plan(): void
    {
        Sanctum::actingAs($this->user);

        $plan = $this->createPlan();
        $plan->periods()->create([
            'year' => 2026,
            'planned_income' => 60000,
            'planned_expense' => 40000,
        ]);

        $response = $this->getJson("/api/v1/finance/plans/{$plan->id}/compare");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'plan',
                    'comparison',
                ],
            ]);
    }

    public function test_user_cannot_access_other_users_plan(): void
    {
        Sanctum::actingAs($this->user);

        $otherUser = User::factory()->create();
        $plan = FinancialPlan::create([
            'user_id' => $otherUser->id,
            'name' => 'Other Plan',
            'start_year' => 2026,
            'end_year' => 2026,
            'currency_code' => 'USD',
            'status' => 'draft',
        ]);

        $response = $this->getJson("/api/v1/finance/plans/{$plan->id}");

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_access_plans(): void
    {
        $response = $this->getJson('/api/v1/finance/plans');

        $response->assertUnauthorized();
    }
}
