<?php

namespace Modules\Finance\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Finance\Models\{Account, Currency, SavingsGoal};
use Tests\TestCase;

class SavingsGoalApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Account $account;

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

        $this->account = Account::create([
            'user_id' => $this->user->id,
            'name' => 'Savings Account',
            'account_type' => 'bank',
            'currency_code' => 'USD',
            'initial_balance' => 5000,
            'current_balance' => 5000,
            'is_active' => true,
        ]);
    }

    protected function createSavingsGoal(array $attributes = []): SavingsGoal
    {
        return SavingsGoal::create(array_merge([
            'user_id' => $this->user->id,
            'name' => 'Emergency Fund',
            'target_amount' => 10000,
            'current_amount' => 0,
            'currency_code' => 'USD',
            'target_date' => now()->addYear(),
            'status' => 'active',
        ], $attributes));
    }

    public function test_user_can_list_savings_goals(): void
    {
        Sanctum::actingAs($this->user);

        $this->createSavingsGoal(['name' => 'Goal 1']);
        $this->createSavingsGoal(['name' => 'Goal 2']);

        $response = $this->getJson('/api/v1/finance/savings-goals');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_user_can_create_savings_goal(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/finance/savings-goals', [
            'name' => 'Vacation Fund',
            'target_amount' => 5000,
            'currency_code' => 'USD',
            'target_date' => now()->addMonths(6)->format('Y-m-d'),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Vacation Fund');
    }

    public function test_user_can_view_savings_goal(): void
    {
        Sanctum::actingAs($this->user);

        $goal = $this->createSavingsGoal();

        $response = $this->getJson("/api/v1/finance/savings-goals/{$goal->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $goal->id);
    }

    public function test_user_can_update_savings_goal(): void
    {
        Sanctum::actingAs($this->user);

        $goal = $this->createSavingsGoal();

        $response = $this->putJson("/api/v1/finance/savings-goals/{$goal->id}", [
            'name' => 'Updated Goal',
            'target_amount' => 15000,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Goal')
            ->assertJsonPath('data.target_amount', 15000);
    }

    public function test_user_can_delete_savings_goal(): void
    {
        Sanctum::actingAs($this->user);

        $goal = $this->createSavingsGoal();

        $response = $this->deleteJson("/api/v1/finance/savings-goals/{$goal->id}");

        $response->assertOk();
        $this->assertSoftDeleted('finance_savings_goals', ['id' => $goal->id]);
    }

    public function test_user_can_contribute_to_goal(): void
    {
        Sanctum::actingAs($this->user);

        $goal = $this->createSavingsGoal();

        $response = $this->postJson("/api/v1/finance/savings-goals/{$goal->id}/contribute", [
            'amount' => 500,
        ]);

        $response->assertCreated();
        $goal->refresh();
        $this->assertEquals(500, $goal->current_amount);
    }

    public function test_user_can_withdraw_from_goal(): void
    {
        Sanctum::actingAs($this->user);

        $goal = $this->createSavingsGoal();

        $this->postJson("/api/v1/finance/savings-goals/{$goal->id}/contribute", [
            'amount' => 1000,
        ]);

        $goal->refresh();
        $previousAmount = $goal->current_amount;

        $response = $this->postJson("/api/v1/finance/savings-goals/{$goal->id}/withdraw", [
            'amount' => 300,
        ]);

        $response->assertCreated();
        $goal->refresh();
        $this->assertEquals($previousAmount - 300, $goal->current_amount);
    }

    public function test_user_can_pause_goal(): void
    {
        Sanctum::actingAs($this->user);

        $goal = $this->createSavingsGoal(['status' => 'active']);

        $response = $this->postJson("/api/v1/finance/savings-goals/{$goal->id}/pause");

        $response->assertOk();
        $goal->refresh();
        $this->assertEquals('paused', $goal->status);
    }

    public function test_user_can_resume_goal(): void
    {
        Sanctum::actingAs($this->user);

        $goal = $this->createSavingsGoal(['status' => 'paused']);

        $response = $this->postJson("/api/v1/finance/savings-goals/{$goal->id}/resume");

        $response->assertOk();
        $goal->refresh();
        $this->assertEquals('active', $goal->status);
    }

    public function test_user_can_get_savings_summary(): void
    {
        Sanctum::actingAs($this->user);

        $this->createSavingsGoal(['target_amount' => 10000, 'current_amount' => 3000]);
        $this->createSavingsGoal(['target_amount' => 5000, 'current_amount' => 2000]);

        $response = $this->getJson('/api/v1/finance/savings-goals-summary');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'total_target',
                    'total_saved',
                    'total_remaining',
                    'overall_progress',
                    'active_goals_count',
                    'completed_goals_count',
                    'total_goals_count',
                ],
            ]);
    }

    public function test_user_cannot_access_other_users_goal(): void
    {
        Sanctum::actingAs($this->user);

        $otherUser = User::factory()->create();
        $goal = SavingsGoal::create([
            'user_id' => $otherUser->id,
            'name' => 'Other Goal',
            'target_amount' => 5000,
            'current_amount' => 0,
            'currency_code' => 'USD',
            'target_date' => now()->addYear(),
            'status' => 'active',
        ]);

        $response = $this->getJson("/api/v1/finance/savings-goals/{$goal->id}");

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_access_goals(): void
    {
        $response = $this->getJson('/api/v1/finance/savings-goals');

        $response->assertUnauthorized();
    }

    public function test_auto_sync_updates_goal_when_account_balance_changes(): void
    {
        $goal = $this->createSavingsGoal([
            'target_account_id' => $this->account->id,
            'target_amount' => 10000,
            'current_amount' => 5000,
        ]);

        $this->account->updateBalance(1000);

        $goal->refresh();
        $this->assertEquals(6000, $goal->current_amount);
    }

    public function test_auto_sync_completes_goal_when_target_reached(): void
    {
        $goal = $this->createSavingsGoal([
            'target_account_id' => $this->account->id,
            'target_amount' => 5000,
            'current_amount' => 4000,
        ]);

        $this->account->updateBalance(1000);

        $goal->refresh();
        $this->assertEquals(6000, $goal->current_amount);
        $this->assertEquals('completed', $goal->status);
        $this->assertNotNull($goal->completed_at);
    }

    public function test_auto_sync_reactivates_goal_when_balance_drops_below_target(): void
    {
        $goal = $this->createSavingsGoal([
            'target_account_id' => $this->account->id,
            'target_amount' => 5000,
            'current_amount' => 5000,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $this->account->updateBalance(-1000);

        $goal->refresh();
        $this->assertEquals(4000, $goal->current_amount);
        $this->assertEquals('active', $goal->status);
        $this->assertNull($goal->completed_at);
    }

    public function test_auto_sync_skips_currency_mismatch(): void
    {
        Currency::create([
            'code' => 'VND',
            'name' => 'Vietnamese Dong',
            'symbol' => 'd',
            'decimal_places' => 0,
        ]);

        $goal = $this->createSavingsGoal([
            'target_account_id' => $this->account->id,
            'currency_code' => 'VND',
            'current_amount' => 1000,
        ]);

        $this->account->updateBalance(2000);

        $goal->refresh();
        $this->assertEquals(1000, $goal->current_amount);
    }

    public function test_auto_sync_skips_paused_goals(): void
    {
        $goal = $this->createSavingsGoal([
            'target_account_id' => $this->account->id,
            'current_amount' => 3000,
            'status' => 'paused',
        ]);

        $this->account->updateBalance(1000);

        $goal->refresh();
        $this->assertEquals(3000, $goal->current_amount);
    }

    public function test_initial_sync_on_goal_creation_with_linked_account(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/finance/savings-goals', [
            'name' => 'Linked Goal',
            'target_amount' => 20000,
            'currency_code' => 'USD',
            'target_account_id' => $this->account->id,
        ]);

        $response->assertCreated();
        $goal = SavingsGoal::where('name', 'Linked Goal')->first();
        $this->assertEquals(5000, $goal->current_amount);
    }

    public function test_sync_on_goal_update_when_account_linked(): void
    {
        Sanctum::actingAs($this->user);

        $goal = $this->createSavingsGoal(['current_amount' => 0]);

        $response = $this->putJson("/api/v1/finance/savings-goals/{$goal->id}", [
            'target_account_id' => $this->account->id,
        ]);

        $response->assertOk();
        $goal->refresh();
        $this->assertEquals(5000, $goal->current_amount);
    }
}
