<?php

namespace Modules\Finance\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Finance\Models\{Account, Category, Currency, Transaction};
use Modules\Finance\Services\TransactionService;
use Tests\TestCase;

class NormalizeTransferTransactionsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $source;

    private Account $destination;

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

        $this->source = $this->createAccount('Source', 1000);
        $this->destination = $this->createAccount('Destination', 500);
    }

    public function test_new_transfer_rows_use_transfer_type_and_have_no_category(): void
    {
        $this->actingAs($this->user);

        $result = app(TransactionService::class)->recordTransfer([
            'from_account_id' => $this->source->id,
            'to_account_id' => $this->destination->id,
            'amount' => 100,
            'transaction_date' => now()->toDateString(),
        ]);

        $outgoing = $result['debit']->fresh();
        $incoming = $result['credit']->fresh();

        $this->assertSame('transfer', $outgoing->transaction_type);
        $this->assertSame('outgoing', $outgoing->transfer_direction);
        $this->assertNull($outgoing->category_id);
        $this->assertSame('transfer', $incoming->transaction_type);
        $this->assertSame('incoming', $incoming->transfer_direction);
        $this->assertNull($incoming->category_id);
        $this->assertEquals(900, $this->source->fresh()->current_balance);
        $this->assertEquals(600, $this->destination->fresh()->current_balance);
    }

    public function test_command_normalizes_legacy_transfer_pair_and_clears_categories(): void
    {
        $category = Category::create([
            'user_id' => $this->user->id,
            'name' => 'Legacy transfer',
            'type' => 'both',
        ]);

        $outgoing = $this->createTransaction($this->source, 'expense', $category->id);
        $incoming = $this->createTransaction($this->destination, 'income', $category->id);
        $outgoing->update(['transfer_transaction_id' => $incoming->id]);
        $incoming->update(['transfer_transaction_id' => $outgoing->id]);

        $this->artisan('finance:normalize-transfers')->assertSuccessful();

        $outgoing->refresh();
        $incoming->refresh();

        $this->assertSame('transfer', $outgoing->transaction_type);
        $this->assertSame('outgoing', $outgoing->transfer_direction);
        $this->assertNull($outgoing->category_id);
        $this->assertSame('transfer', $incoming->transaction_type);
        $this->assertSame('incoming', $incoming->transfer_direction);
        $this->assertNull($incoming->category_id);
    }

    public function test_command_repairs_both_sides_of_a_one_way_legacy_link(): void
    {
        $category = Category::create([
            'user_id' => $this->user->id,
            'name' => 'Legacy transfer',
            'type' => 'both',
        ]);

        $outgoing = $this->createTransaction($this->source, 'expense', $category->id);
        $incoming = $this->createTransaction($this->destination, 'income', $category->id);
        $outgoing->update(['transfer_transaction_id' => $incoming->id]);

        $this->artisan('finance:normalize-transfers')->assertSuccessful();

        $outgoing->refresh();
        $incoming->refresh();

        $this->assertSame('transfer', $outgoing->transaction_type);
        $this->assertSame('outgoing', $outgoing->transfer_direction);
        $this->assertNull($outgoing->category_id);
        $this->assertSame($incoming->id, $outgoing->transfer_transaction_id);
        $this->assertSame($incoming->account_id, $outgoing->transfer_account_id);

        $this->assertSame('transfer', $incoming->transaction_type);
        $this->assertSame('incoming', $incoming->transfer_direction);
        $this->assertNull($incoming->category_id);
        $this->assertSame($outgoing->id, $incoming->transfer_transaction_id);
        $this->assertSame($outgoing->account_id, $incoming->transfer_account_id);
    }

    public function test_command_can_link_and_normalize_an_explicit_unlinked_pair(): void
    {
        $category = Category::create([
            'user_id' => $this->user->id,
            'name' => 'Legacy transfer',
            'type' => 'both',
        ]);

        $outgoing = $this->createTransaction($this->source, 'expense', $category->id);
        $incoming = $this->createTransaction($this->destination, 'income', $category->id);

        $this->artisan('finance:normalize-transfers', [
            '--pair' => ["{$outgoing->id},{$incoming->id}"],
        ])->assertSuccessful();

        $outgoing->refresh();
        $incoming->refresh();

        $this->assertSame('transfer', $outgoing->transaction_type);
        $this->assertSame('outgoing', $outgoing->transfer_direction);
        $this->assertNull($outgoing->category_id);
        $this->assertSame($incoming->id, $outgoing->transfer_transaction_id);

        $this->assertSame('transfer', $incoming->transaction_type);
        $this->assertSame('incoming', $incoming->transfer_direction);
        $this->assertNull($incoming->category_id);
        $this->assertSame($outgoing->id, $incoming->transfer_transaction_id);
    }

    public function test_deleting_normalized_transfer_reverses_both_balance_impacts(): void
    {
        Sanctum::actingAs($this->user);

        $result = app(TransactionService::class)->recordTransfer([
            'from_account_id' => $this->source->id,
            'to_account_id' => $this->destination->id,
            'amount' => 100,
            'transaction_date' => now()->toDateString(),
        ]);

        $this->deleteJson("/api/v1/finance/transactions/{$result['debit']->id}")
            ->assertOk();

        $this->assertEquals(1000, $this->source->fresh()->current_balance);
        $this->assertEquals(500, $this->destination->fresh()->current_balance);
        $this->assertSoftDeleted('finance_transactions', ['id' => $result['debit']->id]);
        $this->assertSoftDeleted('finance_transactions', ['id' => $result['credit']->id]);
    }

    public function test_offline_mobile_transfer_uses_normalized_pair_creation(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson('/api/v1/finance/smart-input/sync-offline', [
            'transactions' => [[
                'offline_id' => 'transfer-1',
                'type' => 'transfer',
                'account_id' => $this->source->id,
                'transfer_account_id' => $this->destination->id,
                'amount' => 100,
                'description' => 'Mobile transfer',
                'transaction_date' => now()->toDateString(),
            ]],
        ])->assertOk()
            ->assertJsonPath('data.synced', 1);

        $transactions = Transaction::where('description', 'Mobile transfer')->get();

        $this->assertCount(2, $transactions);
        $this->assertEqualsCanonicalizing(['incoming', 'outgoing'], $transactions->pluck('transfer_direction')->all());
        $this->assertTrue($transactions->every(fn (Transaction $transaction) => $transaction->transaction_type === 'transfer'));
        $this->assertTrue($transactions->every(fn (Transaction $transaction) => $transaction->category_id === null));
    }

    private function createAccount(string $name, float $balance): Account
    {
        return Account::create([
            'user_id' => $this->user->id,
            'name' => $name,
            'account_type' => 'bank',
            'currency_code' => 'USD',
            'initial_balance' => $balance,
            'current_balance' => $balance,
            'is_active' => true,
        ]);
    }

    private function createTransaction(Account $account, string $type, int $categoryId): Transaction
    {
        return Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $account->id,
            'category_id' => $categoryId,
            'transaction_type' => $type,
            'amount' => 100,
            'currency_code' => 'USD',
            'transaction_date' => now(),
        ]);
    }
}
