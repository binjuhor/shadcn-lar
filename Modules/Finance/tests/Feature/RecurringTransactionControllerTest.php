<?php

namespace Modules\Finance\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Finance\Models\{Account, Currency, RecurringTransaction, Transaction};
use Tests\TestCase;

class RecurringTransactionControllerTest extends TestCase
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

        $this->actingAs($this->user);
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
            'start_date' => now()->subMonth()->startOfMonth(),
            'next_run_date' => now()->subDay(),
            'is_active' => true,
            'auto_create' => true,
        ], $attributes));
    }

    public function test_updating_account_syncs_recurring_currency_code(): void
    {
        Currency::create([
            'code' => 'VND',
            'name' => 'Vietnamese Dong',
            'symbol' => '₫',
            'decimal_places' => 0,
            'is_default' => false,
        ]);

        $vndAccount = Account::create([
            'user_id' => $this->user->id,
            'name' => 'VND Account',
            'account_type' => 'bank',
            'currency_code' => 'VND',
            'initial_balance' => 0,
            'current_balance' => 0,
            'is_active' => true,
        ]);

        $recurring = $this->createRecurring(['currency_code' => 'USD']);

        $this->put(route('dashboard.finance.recurring-transactions.update', $recurring->id), [
            'account_id' => $vndAccount->id,
        ])->assertRedirect();

        $recurring->refresh();

        $this->assertSame($vndAccount->id, $recurring->account_id);
        $this->assertSame('VND', $recurring->currency_code);
    }

    public function test_process_due_creates_transactions_and_advances_schedule(): void
    {
        $recurring = $this->createRecurring();

        $response = $this->post(route('dashboard.finance.recurring-transactions.process'));

        $response->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseCount('finance_transactions', 1);

        $recurring->refresh();
        $this->assertNotNull($recurring->last_run_date);
        $this->assertTrue($recurring->next_run_date->isFuture());
    }

    public function test_process_due_only_touches_current_users_recurrings(): void
    {
        $this->createRecurring();

        $otherUser = User::factory()->create();
        $otherAccount = Account::create([
            'user_id' => $otherUser->id,
            'name' => 'Other Account',
            'account_type' => 'bank',
            'currency_code' => 'USD',
            'initial_balance' => 10000,
            'current_balance' => 10000,
            'is_active' => true,
        ]);

        $otherRecurring = RecurringTransaction::create([
            'user_id' => $otherUser->id,
            'account_id' => $otherAccount->id,
            'name' => 'Other Salary',
            'transaction_type' => 'income',
            'amount' => 7000,
            'currency_code' => 'USD',
            'frequency' => 'monthly',
            'day_of_month' => 1,
            'start_date' => now()->subMonth()->startOfMonth(),
            'next_run_date' => now()->subDay(),
            'is_active' => true,
            'auto_create' => true,
        ]);

        $this->post(route('dashboard.finance.recurring-transactions.process'));

        $otherRecurring->refresh();

        $this->assertNull($otherRecurring->last_run_date);
        $this->assertSame(
            now()->subDay()->toDateString(),
            $otherRecurring->next_run_date->toDateString()
        );
        $this->assertSame(0, Transaction::where('user_id', $otherUser->id)->count());
    }

    public function test_process_command_catches_up_all_missed_occurrences(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 9)->startOfDay());

        $recurring = $this->createRecurring([
            'next_run_date' => '2026-07-01',
            'start_date' => '2026-07-01',
        ]);

        $this->artisan('finance:process-recurring')->assertSuccessful();

        // Jul, Aug, Sep, Oct occurrences
        $this->assertDatabaseCount('finance_transactions', 4);

        $recurring->refresh();
        $this->assertSame('2026-10-01', $recurring->last_run_date->toDateString());
        $this->assertSame('2026-11-01', $recurring->next_run_date->toDateString());
    }

    public function test_process_advances_schedule_without_creating_transactions_when_auto_create_disabled(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 9)->startOfDay());

        $recurring = $this->createRecurring([
            'next_run_date' => '2026-08-01',
            'auto_create' => false,
        ]);

        $this->artisan('finance:process-recurring')->assertSuccessful();

        $this->assertDatabaseCount('finance_transactions', 0);
        $this->assertSame('2026-11-01', $recurring->refresh()->next_run_date->toDateString());
    }

    public function test_process_runs_occurrences_missed_before_end_date(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 9)->startOfDay());

        $recurring = $this->createRecurring([
            'next_run_date' => '2026-08-01',
            'end_date' => '2026-09-15',
        ]);

        $this->artisan('finance:process-recurring')->assertSuccessful();

        // Aug and Sep only; Oct is past end_date
        $this->assertDatabaseCount('finance_transactions', 2);
        $this->assertSame('2026-09-01', $recurring->refresh()->last_run_date->toDateString());
    }
}
