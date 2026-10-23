<?php

namespace Modules\Finance\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Modules\Finance\Models\{Account, Borrowing, BorrowingRepayment, Currency, Transaction};
use Modules\Finance\Services\BorrowingService;
use Tests\TestCase;

class BorrowingApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Account $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'is_default' => true]);

        $this->account = Account::create([
            'user_id' => $this->user->id,
            'name' => 'Checking',
            'account_type' => 'bank',
            'currency_code' => 'USD',
            'initial_balance' => 1000,
            'current_balance' => 1000,
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->user);
    }

    public function test_user_can_create_list_show_update_and_delete_own_borrowing(): void
    {
        $created = $this->postJson('/api/v1/finance/borrowings', [
            'lender_name' => 'Taylor', 'amount' => 500, 'currency_code' => 'USD',
            'borrowed_date' => '2026-08-01', 'due_date' => '2026-09-01',
        ])->assertCreated()->assertJsonPath('data.remaining_amount', 500);

        $borrowingId = $created->json('data.id');
        $this->getJson('/api/v1/finance/borrowings')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/finance/borrowings/{$borrowingId}")->assertOk()->assertJsonPath('data.lender_name', 'Taylor');
        $this->patchJson("/api/v1/finance/borrowings/{$borrowingId}", ['lender_name' => 'Morgan'])
            ->assertOk()->assertJsonPath('data.lender_name', 'Morgan');
        $this->deleteJson("/api/v1/finance/borrowings/{$borrowingId}")->assertOk();
        $this->assertSoftDeleted('finance_borrowings', ['id' => $borrowingId]);
    }

    public function test_user_cannot_access_or_mutate_another_users_borrowing(): void
    {
        $borrowing = Borrowing::factory()->create(['user_id' => User::factory()->create()->id, 'currency_code' => 'USD']);

        $this->getJson("/api/v1/finance/borrowings/{$borrowing->id}")->assertForbidden();
        $this->patchJson("/api/v1/finance/borrowings/{$borrowing->id}", ['lender_name' => 'No'])->assertForbidden();
        $this->postJson("/api/v1/finance/borrowings/{$borrowing->id}/repayments", [
            'amount' => 1, 'repaid_at' => '2026-08-01',
        ])->assertForbidden();
        $this->deleteJson("/api/v1/finance/borrowings/{$borrowing->id}")->assertForbidden();
    }

    public function test_repayments_update_summary_and_can_be_deleted(): void
    {
        $borrowing = Borrowing::factory()->create(['user_id' => $this->user->id, 'amount' => 500, 'amount_repaid' => 0, 'currency_code' => 'USD']);

        $repaymentId = $this->postJson("/api/v1/finance/borrowings/{$borrowing->id}/repayments", [
            'amount' => 125, 'repaid_at' => '2026-08-10', 'note' => 'Partial',
        ])->assertCreated()->json('data.id');

        $this->assertDatabaseHas('finance_borrowings', ['id' => $borrowing->id, 'amount_repaid' => 125, 'status' => 'partially_repaid']);
        $this->getJson('/api/v1/finance/borrowings-summary')->assertOk()->assertJsonPath('data.borrowings_payable', 375);

        $this->deleteJson("/api/v1/finance/borrowings/{$borrowing->id}/repayments/{$repaymentId}")->assertOk();
        $this->assertDatabaseHas('finance_borrowings', ['id' => $borrowing->id, 'amount_repaid' => 0, 'status' => 'active']);
    }

    public function test_repayment_cannot_exceed_remaining_principal(): void
    {
        $borrowing = Borrowing::factory()->create(['user_id' => $this->user->id, 'amount' => 100, 'amount_repaid' => 0, 'currency_code' => 'USD']);

        $this->postJson("/api/v1/finance/borrowings/{$borrowing->id}/repayments", [
            'amount' => 101, 'repaid_at' => '2026-08-10',
        ])->assertUnprocessable()->assertJsonValidationErrors('amount');
    }

    public function test_repayment_must_belong_to_route_borrowing(): void
    {
        $borrowing = Borrowing::factory()->create(['user_id' => $this->user->id, 'currency_code' => 'USD']);
        $other = Borrowing::factory()->create(['user_id' => $this->user->id, 'currency_code' => 'USD']);
        $repayment = BorrowingRepayment::create(['borrowing_id' => $other->id, 'amount' => 1, 'repaid_at' => '2026-08-10']);

        $this->deleteJson("/api/v1/finance/borrowings/{$borrowing->id}/repayments/{$repayment->id}")->assertNotFound();
    }

    public function test_written_off_borrowing_rejects_repayment(): void
    {
        $borrowing = Borrowing::factory()->create(['user_id' => $this->user->id, 'amount' => 100, 'status' => 'written_off', 'currency_code' => 'USD']);

        $this->postJson("/api/v1/finance/borrowings/{$borrowing->id}/repayments", [
            'amount' => 10, 'repaid_at' => '2026-08-10',
        ])->assertUnprocessable()->assertJsonValidationErrors('amount');
    }

    public function test_currency_cannot_change_after_repayment(): void
    {
        Currency::create(['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'decimal_places' => 2]);
        $borrowing = Borrowing::factory()->create(['user_id' => $this->user->id, 'amount' => 100, 'amount_repaid' => 10, 'currency_code' => 'USD']);
        BorrowingRepayment::create(['borrowing_id' => $borrowing->id, 'amount' => 10, 'repaid_at' => '2026-08-10']);

        $this->patchJson("/api/v1/finance/borrowings/{$borrowing->id}", ['currency_code' => 'EUR'])
            ->assertUnprocessable()->assertJsonValidationErrors('currency_code');
    }

    public function test_linked_transaction_must_be_an_income_transaction(): void
    {
        $expense = Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'transaction_type' => 'expense',
            'amount' => 100,
            'currency_code' => 'USD',
            'description' => 'Expense',
            'transaction_date' => '2026-08-01',
        ]);

        $this->postJson('/api/v1/finance/borrowings', [
            'transaction_id' => $expense->id,
            'lender_name' => 'Taylor', 'amount' => 100, 'currency_code' => 'USD',
            'borrowed_date' => '2026-08-01',
        ])->assertUnprocessable()->assertJsonValidationErrors('transaction_id');
    }

    public function test_service_rechecks_remaining_principal_inside_transaction(): void
    {
        $borrowing = Borrowing::factory()->create(['user_id' => $this->user->id, 'amount' => 100, 'currency_code' => 'USD']);
        app(BorrowingService::class)->addRepayment($borrowing, ['amount' => 80, 'repaid_at' => '2026-08-10']);

        $this->expectException(ValidationException::class);
        app(BorrowingService::class)->addRepayment($borrowing, ['amount' => 30, 'repaid_at' => '2026-08-11']);
    }

    public function test_service_rechecks_amount_against_current_repayments_on_update(): void
    {
        $borrowing = Borrowing::factory()->create(['user_id' => $this->user->id, 'amount' => 100, 'currency_code' => 'USD']);
        app(BorrowingService::class)->addRepayment($borrowing, ['amount' => 80, 'repaid_at' => '2026-08-10']);

        $this->expectException(ValidationException::class);
        app(BorrowingService::class)->updateBorrowing($borrowing, ['amount' => 50]);
    }
}
