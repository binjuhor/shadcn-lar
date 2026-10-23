<?php

namespace Modules\Finance\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Finance\Models\{Account, Borrowing, Currency, Transaction};
use Tests\TestCase;

class BorrowingControllerTest extends TestCase
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
            'name' => 'Checking',
            'account_type' => 'bank',
            'currency_code' => 'USD',
            'initial_balance' => 1000,
            'current_balance' => 1000,
            'is_active' => true,
        ]);

        $this->actingAs($this->user);
    }

    public function test_store_persists_selected_income_transaction_on_borrowing(): void
    {
        $transaction = $this->transaction([
            'amount' => 125,
            'description' => 'Borrowed from Alex',
            'transaction_date' => '2026-07-07',
        ]);

        $response = $this->post(route('dashboard.finance.borrowings.store'), [
            'transaction_id' => $transaction->id,
            'lender_name' => 'Alex',
            'amount' => 125,
            'currency_code' => 'USD',
            'borrowed_date' => '2026-07-07',
            'due_date' => null,
            'message' => 'Emergency cash',
        ]);

        $response->assertRedirect(route('dashboard.finance.borrowings.index'));

        $this->assertDatabaseHas('finance_borrowings', [
            'user_id' => $this->user->id,
            'transaction_id' => $transaction->id,
            'lender_name' => 'Alex',
            'amount' => 125,
        ]);
    }

    public function test_index_orders_borrowings_by_linked_transaction_history_then_borrowed_date(): void
    {
        $recentTransaction = $this->transaction([
            'description' => 'Recent linked transaction',
            'transaction_date' => '2026-07-07',
        ]);
        $olderTransaction = $this->transaction([
            'description' => 'Older linked transaction',
            'transaction_date' => '2026-06-01',
        ]);

        Borrowing::create([
            'user_id' => $this->user->id,
            'transaction_id' => $olderTransaction->id,
            'lender_name' => 'Older linked',
            'amount' => 100,
            'amount_repaid' => 0,
            'currency_code' => 'USD',
            'borrowed_date' => '2026-07-10',
            'status' => 'active',
            'created_at' => '2026-07-10 00:00:00',
        ]);
        Borrowing::create([
            'user_id' => $this->user->id,
            'lender_name' => 'Unlinked middle',
            'amount' => 100,
            'amount_repaid' => 0,
            'currency_code' => 'USD',
            'borrowed_date' => '2026-06-15',
            'status' => 'active',
            'created_at' => '2026-06-15 00:00:00',
        ]);
        Borrowing::create([
            'user_id' => $this->user->id,
            'transaction_id' => $recentTransaction->id,
            'lender_name' => 'Recent linked',
            'amount' => 100,
            'amount_repaid' => 0,
            'currency_code' => 'USD',
            'borrowed_date' => '2026-05-01',
            'status' => 'repaid',
            'created_at' => '2026-05-01 00:00:00',
        ]);

        $response = $this->get(route('dashboard.finance.borrowings.index'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Finance::borrowings/index', false)
                ->where('borrowings.data.0.lender_name', 'Recent linked')
                ->where('borrowings.data.1.lender_name', 'Unlinked middle')
                ->where('borrowings.data.2.lender_name', 'Older linked')
            );
    }

    public function test_recording_repayment_updates_borrowing_status(): void
    {
        $borrowing = Borrowing::create([
            'user_id' => $this->user->id,
            'lender_name' => 'Alex',
            'amount' => 500,
            'amount_repaid' => 0,
            'currency_code' => 'USD',
            'borrowed_date' => '2026-07-07',
            'status' => 'active',
        ]);

        $this->post(route('dashboard.finance.borrowings.repayments.store', $borrowing->id), [
            'amount' => 500,
            'repaid_at' => '2026-07-20',
        ])->assertRedirect();

        $this->assertDatabaseHas('finance_borrowings', [
            'id' => $borrowing->id,
            'amount_repaid' => 500,
            'status' => 'repaid',
        ]);
    }

    public function test_user_cannot_update_another_users_borrowing(): void
    {
        $borrowing = Borrowing::create([
            'user_id' => User::factory()->create()->id,
            'lender_name' => 'Someone',
            'amount' => 100,
            'amount_repaid' => 0,
            'currency_code' => 'USD',
            'borrowed_date' => '2026-07-07',
            'status' => 'active',
        ]);

        $this->put(route('dashboard.finance.borrowings.update', $borrowing->id), [
            'lender_name' => 'Hacked',
        ])->assertForbidden();
    }

    protected function transaction(array $attributes = []): Transaction
    {
        return Transaction::create(array_merge([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'transaction_type' => 'income',
            'amount' => 100,
            'currency_code' => 'USD',
            'description' => 'Borrowed money',
            'transaction_date' => now()->toDateString(),
        ], $attributes));
    }
}
