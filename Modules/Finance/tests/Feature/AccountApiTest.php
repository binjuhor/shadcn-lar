<?php

namespace Modules\Finance\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\Currency;
use Tests\TestCase;

class AccountApiTest extends TestCase
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

    protected function createAccount(array $attributes = []): Account
    {
        return Account::create(array_merge([
            'user_id' => $this->user->id,
            'name' => 'Test Account',
            'account_type' => 'bank',
            'currency_code' => 'USD',
            'initial_balance' => 1000,
            'current_balance' => 1000,
            'is_active' => true,
        ], $attributes));
    }

    public function test_user_can_list_accounts(): void
    {
        Sanctum::actingAs($this->user);

        $this->createAccount(['name' => 'Account 1']);
        $this->createAccount(['name' => 'Account 2']);
        $this->createAccount(['name' => 'Account 3']);

        $response = $this->getJson('/api/v1/finance/accounts');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_user_can_create_account(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/finance/accounts', [
            'name' => 'My Bank Account',
            'account_type' => 'bank',
            'currency_code' => 'USD',
            'initial_balance' => 1000.50,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'My Bank Account')
            ->assertJsonPath('data.initial_balance', 1000.50);
    }

    public function test_user_can_view_own_account(): void
    {
        Sanctum::actingAs($this->user);

        $account = $this->createAccount();

        $response = $this->getJson("/api/v1/finance/accounts/{$account->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $account->id);
    }

    public function test_user_cannot_view_other_users_account(): void
    {
        Sanctum::actingAs($this->user);

        $otherUser = User::factory()->create();
        $account = $this->createAccount(['user_id' => $otherUser->id]);

        $response = $this->getJson("/api/v1/finance/accounts/{$account->id}");

        $response->assertForbidden();
    }

    public function test_user_can_update_account(): void
    {
        Sanctum::actingAs($this->user);

        $account = $this->createAccount();

        $response = $this->putJson("/api/v1/finance/accounts/{$account->id}", [
            'name' => 'Updated Name',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Name');
    }

    public function test_user_can_delete_account_without_transactions(): void
    {
        Sanctum::actingAs($this->user);

        $account = $this->createAccount();

        $response = $this->deleteJson("/api/v1/finance/accounts/{$account->id}");

        $response->assertOk();
        $this->assertSoftDeleted('finance_accounts', ['id' => $account->id]);
    }

    public function test_unauthenticated_user_cannot_access_accounts(): void
    {
        $response = $this->getJson('/api/v1/finance/accounts');

        $response->assertUnauthorized();
    }
}
