<?php

namespace Modules\Finance\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Finance\Models\{Account, Currency, RecurringTransaction};
use Modules\Finance\Services\TransactionService;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function test_updating_account_syncs_recurring_currency_code(): void
    {
        Sanctum::actingAs($this->user);

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

        $this->putJson("/api/v1/finance/recurring-transactions/{$recurring->id}", [
            'account_id' => $vndAccount->id,
        ])->assertOk();

        $recurring->refresh();

        $this->assertSame($vndAccount->id, $recurring->account_id);
        $this->assertSame('VND', $recurring->currency_code);
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

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function nextRunScheduleProvider(): array
    {
        // Today is 2026-10-15 (Thursday).
        return [
            'monthly day later this month' => [['frequency' => 'monthly', 'day_of_month' => 25, 'start_date' => '2026-10-01'], '2026-10-25'],
            'monthly day already passed' => [['frequency' => 'monthly', 'day_of_month' => 5, 'start_date' => '2026-10-01'], '2026-11-05'],
            'monthly future start uses the setting day' => [['frequency' => 'monthly', 'day_of_month' => 10, 'start_date' => '2026-12-03'], '2026-12-10'],
            'monthly day clamps to short month' => [['frequency' => 'monthly', 'day_of_month' => 31, 'start_date' => '2026-11-01'], '2026-11-30'],
            'monthly today rolls to next month' => [['frequency' => 'monthly', 'day_of_month' => 15, 'start_date' => '2026-01-15'], '2026-11-15'],
            'yearly month and day' => [['frequency' => 'yearly', 'month_of_year' => 3, 'day_of_month' => 20, 'start_date' => '2026-10-01'], '2027-03-20'],
            'yearly later this year' => [['frequency' => 'yearly', 'month_of_year' => 12, 'day_of_month' => 1, 'start_date' => '2025-12-01'], '2026-12-01'],
            'weekly day of week' => [['frequency' => 'weekly', 'day_of_week' => 1, 'start_date' => '2026-10-01'], '2026-10-19'],
            'daily future start' => [['frequency' => 'daily', 'start_date' => '2026-10-20'], '2026-10-20'],
            'daily past start' => [['frequency' => 'daily', 'start_date' => '2026-10-01'], '2026-10-16'],
        ];
    }

    #[DataProvider('nextRunScheduleProvider')]
    public function test_next_run_follows_schedule_settings_not_start_date(array $schedule, string $expected): void
    {
        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(9, 0));
        Sanctum::actingAs($this->user);

        $this->postJson('/api/v1/finance/recurring-transactions', [
            'name' => 'Internet',
            'account_id' => $this->account->id,
            'transaction_type' => 'expense',
            'amount' => 300,
            ...$schedule,
        ])->assertCreated();

        $this->assertSame($expected, RecurringTransaction::where('name', 'Internet')->sole()->next_run_date->toDateString());
    }

    public function test_changing_day_of_month_recalculates_next_run(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(9, 0));
        Sanctum::actingAs($this->user);
        $recurring = $this->createRecurring(['start_date' => '2026-10-01', 'day_of_month' => 1, 'next_run_date' => '2026-11-01']);

        $this->putJson("/api/v1/finance/recurring-transactions/{$recurring->id}", ['day_of_month' => 28])->assertOk();

        $this->assertSame('2026-10-28', $recurring->refresh()->next_run_date->toDateString());
    }

    public function test_matching_manual_transaction_advances_recurring_schedule(): void
    {
        Sanctum::actingAs($this->user);

        $recurring = $this->createRecurring([
            'name' => 'Monthly Rent',
            'transaction_type' => 'expense',
            'amount' => 1500,
            'start_date' => '2026-06-01',
            'next_run_date' => '2026-07-01',
            'day_of_month' => 1,
        ]);

        app(TransactionService::class)->recordExpense([
            'account_id' => $this->account->id,
            'amount' => 1500,
            'description' => 'Monthly Rent',
            'transaction_date' => '2026-07-01',
        ]);

        $recurring->refresh();

        $this->assertSame('2026-07-01', $recurring->last_run_date->toDateString());
        $this->assertSame('2026-08-01', $recurring->next_run_date->toDateString());
    }

    public function test_unmatched_manual_transaction_does_not_advance_recurring_schedule(): void
    {
        Sanctum::actingAs($this->user);

        $recurring = $this->createRecurring([
            'name' => 'Monthly Rent',
            'transaction_type' => 'expense',
            'amount' => 1500,
            'start_date' => '2026-06-01',
            'next_run_date' => '2026-07-01',
            'day_of_month' => 1,
        ]);

        app(TransactionService::class)->recordExpense([
            'account_id' => $this->account->id,
            'amount' => 1500,
            'description' => 'Office Supplies',
            'transaction_date' => '2026-07-01',
        ]);

        $recurring->refresh();

        $this->assertNull($recurring->last_run_date);
        $this->assertSame('2026-07-01', $recurring->next_run_date->toDateString());
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
