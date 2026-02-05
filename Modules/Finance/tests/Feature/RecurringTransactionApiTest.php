<?php

namespace Modules\Finance\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Finance\Models\{Account, Currency, RecurringTransaction};
use Tests\TestCase;

class RecurringTransactionApiTest extends TestCase
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
            'name' => 'Test Account',
            'account_type' => 'bank',
            'currency_code' => 'USD',
            'initial_balance' => 10000,
            'current_balance' => 10000,
            'is_active' => true,
        ]);
    }

    protected function createRecurring(array $attributes = []): RecurringTransaction
    {
        return RecurringTransaction::create(array_merge([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'name' => 'Monthly Salary',
            'transaction_type' => 'income',
            'amount' => 5000,
            'currency_code' => 'USD',
            'frequency' => 'monthly',
            'day_of_month' => 1,
            'start_date' => now()->startOfMonth(),
            'next_run_date' => now()->addMonth()->startOfMonth(),
            'is_active' => true,
            'auto_create' => true,
        ], $attributes));
    }

    public function test_user_can_list_recurring_transactions(): void
    {
        Sanctum::actingAs($this->user);

        $this->createRecurring(['name' => 'Salary']);
        $this->createRecurring(['name' => 'Rent', 'transaction_type' => 'expense']);

        $response = $this->getJson('/api/v1/finance/recurring-transactions');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_user_can_filter_active_only(): void
    {
        Sanctum::actingAs($this->user);

        $this->createRecurring(['name' => 'Active', 'is_active' => true]);
        $this->createRecurring(['name' => 'Inactive', 'is_active' => false]);

        $response = $this->getJson('/api/v1/finance/recurring-transactions?active_only=1');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_user_can_create_recurring_transaction(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/finance/recurring-transactions', [
            'name' => 'Monthly Rent',
            'account_id' => $this->account->id,
            'transaction_type' => 'expense',
            'amount' => 1500,
            'frequency' => 'monthly',
            'day_of_month' => 1,
            'start_date' => now()->format('Y-m-d'),
            'is_active' => true,
            'auto_create' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Monthly Rent')
            ->assertJsonPath('data.amount', 1500);
    }

    public function test_user_can_view_recurring_transaction(): void
    {
        Sanctum::actingAs($this->user);

        $recurring = $this->createRecurring();

        $response = $this->getJson("/api/v1/finance/recurring-transactions/{$recurring->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $recurring->id);
    }

    public function test_user_can_update_recurring_transaction(): void
    {
        Sanctum::actingAs($this->user);

        $recurring = $this->createRecurring();

        $response = $this->putJson("/api/v1/finance/recurring-transactions/{$recurring->id}", [
            'name' => 'Updated Name',
            'amount' => 6000,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.amount', 6000);
    }

    public function test_user_can_delete_recurring_transaction(): void
    {
        Sanctum::actingAs($this->user);

        $recurring = $this->createRecurring();

        $response = $this->deleteJson("/api/v1/finance/recurring-transactions/{$recurring->id}");

        $response->assertOk();
        $this->assertSoftDeleted('finance_recurring_transactions', ['id' => $recurring->id]);
    }

    public function test_user_can_toggle_recurring_transaction(): void
    {
        Sanctum::actingAs($this->user);

        $recurring = $this->createRecurring(['is_active' => true]);

        $response = $this->postJson("/api/v1/finance/recurring-transactions/{$recurring->id}/toggle");

        $response->assertOk();
        $recurring->refresh();
        $this->assertFalse($recurring->is_active);
    }

    public function test_user_can_get_preview(): void
    {
        Sanctum::actingAs($this->user);

        $recurring = $this->createRecurring();

        $response = $this->getJson("/api/v1/finance/recurring-transactions/{$recurring->id}/preview");

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_user_can_get_upcoming(): void
    {
        Sanctum::actingAs($this->user);

        $this->createRecurring(['next_run_date' => now()->addDays(5)]);

        $response = $this->getJson('/api/v1/finance/recurring-transactions-upcoming?days=30');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_user_can_get_projection(): void
    {
        Sanctum::actingAs($this->user);

        $this->createRecurring();

        $response = $this->getJson('/api/v1/finance/recurring-transactions-projection');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_user_cannot_access_other_users_recurring(): void
    {
        Sanctum::actingAs($this->user);

        $otherUser = User::factory()->create();
        $otherAccount = Account::create([
            'user_id' => $otherUser->id,
            'name' => 'Other Account',
            'account_type' => 'bank',
            'currency_code' => 'USD',
            'initial_balance' => 5000,
            'current_balance' => 5000,
            'is_active' => true,
        ]);

        $recurring = RecurringTransaction::create([
            'user_id' => $otherUser->id,
            'account_id' => $otherAccount->id,
            'name' => 'Other Recurring',
            'transaction_type' => 'expense',
            'amount' => 100,
            'currency_code' => 'USD',
            'frequency' => 'monthly',
            'start_date' => now(),
            'next_run_date' => now()->addMonth(),
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/v1/finance/recurring-transactions/{$recurring->id}");

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_access_recurring(): void
    {
        $response = $this->getJson('/api/v1/finance/recurring-transactions');

        $response->assertUnauthorized();
    }
}
