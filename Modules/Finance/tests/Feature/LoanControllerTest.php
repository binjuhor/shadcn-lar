<?php

namespace Modules\Finance\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Finance\Models\{Account, Category, Currency, Loan, Transaction};
use Tests\TestCase;

class LoanControllerTest extends TestCase
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

    public function test_store_persists_selected_transaction_on_loan(): void
    {
        $transaction = $this->transaction([
            'amount' => 125,
            'description' => 'Loan to Alex',
            'transaction_date' => '2026-07-07',
        ]);

        $response = $this->post(route('dashboard.finance.loans.store'), [
            'transaction_id' => $transaction->id,
            'borrower_name' => 'Alex',
            'amount' => 125,
            'currency_code' => 'USD',
            'loan_date' => '2026-07-07',
            'due_date' => null,
            'message' => 'Dinner advance',
        ]);

        $response->assertRedirect(route('dashboard.finance.loans.index'));

        $this->assertDatabaseHas('finance_loans', [
            'user_id' => $this->user->id,
            'transaction_id' => $transaction->id,
            'borrower_name' => 'Alex',
            'amount' => 125,
        ]);
    }

    public function test_loan_category_transaction_creates_loan_linked_to_transaction(): void
    {
        $category = Category::create([
            'user_id' => $this->user->id,
            'name' => 'Personal Loan',
            'type' => 'expense',
            'is_active' => true,
            'is_loan' => true,
        ]);

        $response = $this->post(route('dashboard.finance.transactions.store'), [
            'type' => 'expense',
            'account_id' => $this->account->id,
            'category_id' => $category->id,
            'amount' => 200,
            'description' => 'Mina',
            'notes' => 'Loan transfer',
            'transaction_date' => '2026-07-07',
        ]);

        $response->assertRedirect();

        $transaction = Transaction::where('user_id', $this->user->id)
            ->where('description', 'Mina')
            ->firstOrFail();

        $loan = Loan::where('transaction_id', $transaction->id)->first();

        $this->assertNotNull($loan);
        $this->assertSame('Mina', $loan->borrower_name);
        $this->assertSame(200.0, $loan->amount);
        $this->assertSame('USD', $loan->currency_code);
    }

    public function test_index_orders_loans_by_linked_transaction_history_then_loan_date(): void
    {
        $recentTransaction = $this->transaction([
            'description' => 'Recent linked transaction',
            'transaction_date' => '2026-07-07',
        ]);
        $olderTransaction = $this->transaction([
            'description' => 'Older linked transaction',
            'transaction_date' => '2026-06-01',
        ]);

        Loan::create([
            'user_id' => $this->user->id,
            'transaction_id' => $olderTransaction->id,
            'borrower_name' => 'Older linked',
            'amount' => 100,
            'amount_repaid' => 0,
            'currency_code' => 'USD',
            'loan_date' => '2026-07-10',
            'status' => 'active',
            'created_at' => '2026-07-10 00:00:00',
        ]);
        Loan::create([
            'user_id' => $this->user->id,
            'borrower_name' => 'Unlinked middle',
            'amount' => 100,
            'amount_repaid' => 0,
            'currency_code' => 'USD',
            'loan_date' => '2026-06-15',
            'status' => 'active',
            'created_at' => '2026-06-15 00:00:00',
        ]);
        Loan::create([
            'user_id' => $this->user->id,
            'transaction_id' => $recentTransaction->id,
            'borrower_name' => 'Recent linked',
            'amount' => 100,
            'amount_repaid' => 0,
            'currency_code' => 'USD',
            'loan_date' => '2026-05-01',
            'status' => 'repaid',
            'created_at' => '2026-05-01 00:00:00',
        ]);

        $response = $this->get(route('dashboard.finance.loans.index'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Finance::loans/index', false)
                ->where('loans.data.0.borrower_name', 'Recent linked')
                ->where('loans.data.1.borrower_name', 'Unlinked middle')
                ->where('loans.data.2.borrower_name', 'Older linked')
            );
    }

    protected function transaction(array $attributes = []): Transaction
    {
        return Transaction::create(array_merge([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'transaction_type' => 'expense',
            'amount' => 100,
            'currency_code' => 'USD',
            'description' => 'Loan expense',
            'transaction_date' => now()->toDateString(),
        ], $attributes));
    }
}
