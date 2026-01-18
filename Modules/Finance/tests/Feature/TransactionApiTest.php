<?php

namespace Modules\Finance\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Finance\Models\{Account, Category, Currency, Transaction};
use Tests\TestCase;

class TransactionApiTest extends TestCase
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

    protected function createTransaction(array $attributes = []): Transaction
    {
        return Transaction::create(array_merge([
            'account_id' => $this->account->id,
            'user_id' => $this->user->id,
            'transaction_type' => 'expense',
            'amount' => 100,
            'currency_code' => 'USD',
            'transaction_date' => now(),
            'description' => 'Test Transaction',
        ], $attributes));
    }

    public function test_user_can_update_transaction_amount(): void
    {
        Sanctum::actingAs($this->user);

        $transaction = $this->createTransaction(['amount' => 100]);

        // Manually adjust balance for test (simulating what store would do)
        $this->account->update(['current_balance' => 9900]);

        $response = $this->putJson("/api/v1/finance/transactions/{$transaction->id}", [
            'amount' => 150,
        ]);

        $response->assertOk();
        $this->assertEquals(150, $response->json('data.amount'));

        // Balance should be adjusted: 9900 - 50 (extra expense) = 9850
        $this->account->refresh();
        $this->assertEquals(9850, $this->account->current_balance);
    }

    public function test_user_can_update_transaction_date(): void
    {
        Sanctum::actingAs($this->user);

        $transaction = $this->createTransaction();
        $newDate = now()->subDays(5)->format('Y-m-d');

        $response = $this->putJson("/api/v1/finance/transactions/{$transaction->id}", [
            'transaction_date' => $newDate,
        ]);

        $response->assertOk();
        $this->assertEquals($newDate, $response->json('data.transaction_date'));
    }

    public function test_user_can_update_transaction_description(): void
    {
        Sanctum::actingAs($this->user);

        $transaction = $this->createTransaction();

        $response = $this->putJson("/api/v1/finance/transactions/{$transaction->id}", [
            'description' => 'Updated Description',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.description', 'Updated Description');
    }

    public function test_user_can_update_transaction_category(): void
    {
        Sanctum::actingAs($this->user);

        $category = Category::create([
            'name' => 'Food',
            'type' => 'expense',
            'color' => '#ff0000',
        ]);

        $transaction = $this->createTransaction();

        $response = $this->putJson("/api/v1/finance/transactions/{$transaction->id}", [
            'category_id' => $category->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.category_id', $category->id);
    }

    public function test_user_cannot_update_transfer_transaction(): void
    {
        Sanctum::actingAs($this->user);

        $otherAccount = Account::create([
            'user_id' => $this->user->id,
            'name' => 'Other Account',
            'account_type' => 'bank',
            'currency_code' => 'USD',
            'initial_balance' => 5000,
            'current_balance' => 5000,
            'is_active' => true,
        ]);

        // Create linked transfer transactions
        $debitTx = $this->createTransaction([
            'transaction_type' => 'expense',
            'transfer_account_id' => $otherAccount->id,
        ]);

        $creditTx = $this->createTransaction([
            'account_id' => $otherAccount->id,
            'transaction_type' => 'income',
            'transfer_account_id' => $this->account->id,
        ]);

        $debitTx->update(['transfer_transaction_id' => $creditTx->id]);
        $creditTx->update(['transfer_transaction_id' => $debitTx->id]);

        $response = $this->putJson("/api/v1/finance/transactions/{$debitTx->id}", [
            'amount' => 200,
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Transfer transactions cannot be edited. Please delete and create a new transfer.');
    }

    public function test_user_cannot_update_other_users_transaction(): void
    {
        Sanctum::actingAs($this->user);

        $otherUser = User::factory()->create();
        $otherAccount = Account::create([
            'user_id' => $otherUser->id,
            'name' => 'Other User Account',
            'account_type' => 'bank',
            'currency_code' => 'USD',
            'initial_balance' => 5000,
            'current_balance' => 5000,
            'is_active' => true,
        ]);

        $transaction = Transaction::create([
            'account_id' => $otherAccount->id,
            'user_id' => $otherUser->id,
            'transaction_type' => 'expense',
            'amount' => 100,
            'currency_code' => 'USD',
            'transaction_date' => now(),
        ]);

        $response = $this->putJson("/api/v1/finance/transactions/{$transaction->id}", [
            'amount' => 200,
        ]);

        $response->assertForbidden();
    }

    public function test_user_can_reconcile_transaction(): void
    {
        Sanctum::actingAs($this->user);

        $transaction = $this->createTransaction();

        $response = $this->postJson("/api/v1/finance/transactions/{$transaction->id}/reconcile");

        $response->assertOk()
            ->assertJsonPath('message', 'Transaction reconciled successfully');

        $transaction->refresh();
        $this->assertNotNull($transaction->reconciled_at);
    }

    public function test_user_can_unreconcile_transaction(): void
    {
        Sanctum::actingAs($this->user);

        $transaction = $this->createTransaction(['reconciled_at' => now()]);

        $response = $this->postJson("/api/v1/finance/transactions/{$transaction->id}/unreconcile");

        $response->assertOk()
            ->assertJsonPath('message', 'Transaction unreconciled successfully');

        $transaction->refresh();
        $this->assertNull($transaction->reconciled_at);
    }

    public function test_unauthenticated_user_cannot_update_transaction(): void
    {
        $transaction = $this->createTransaction();

        $response = $this->putJson("/api/v1/finance/transactions/{$transaction->id}", [
            'amount' => 200,
        ]);

        $response->assertUnauthorized();
    }
}
