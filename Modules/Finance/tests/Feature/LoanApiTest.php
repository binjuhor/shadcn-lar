<?php

namespace Modules\Finance\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Modules\Finance\Models\{Currency, Loan, LoanRepayment};
use Modules\Finance\Services\LoanService;
use Tests\TestCase;

class LoanApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'is_default' => true]);
        Sanctum::actingAs($this->user);
    }

    public function test_user_can_create_list_show_update_and_delete_own_loan(): void
    {
        $created = $this->postJson('/api/v1/finance/loans', [
            'borrower_name' => 'Taylor', 'amount' => 500, 'currency_code' => 'USD',
            'loan_date' => '2026-08-01', 'due_date' => '2026-09-01',
        ])->assertCreated()->assertJsonPath('data.remaining_amount', 500);

        $loanId = $created->json('data.id');
        $this->getJson('/api/v1/finance/loans')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/finance/loans/{$loanId}")->assertOk()->assertJsonPath('data.borrower_name', 'Taylor');
        $this->patchJson("/api/v1/finance/loans/{$loanId}", ['borrower_name' => 'Morgan'])
            ->assertOk()->assertJsonPath('data.borrower_name', 'Morgan');
        $this->deleteJson("/api/v1/finance/loans/{$loanId}")->assertOk();
        $this->assertSoftDeleted('finance_loans', ['id' => $loanId]);
    }

    public function test_user_cannot_access_or_mutate_another_users_loan(): void
    {
        $loan = Loan::factory()->create(['user_id' => User::factory()->create()->id, 'currency_code' => 'USD']);

        $this->getJson("/api/v1/finance/loans/{$loan->id}")->assertForbidden();
        $this->patchJson("/api/v1/finance/loans/{$loan->id}", ['borrower_name' => 'No'])->assertForbidden();
        $this->postJson("/api/v1/finance/loans/{$loan->id}/repayments", [
            'amount' => 1, 'repaid_at' => '2026-08-01',
        ])->assertForbidden();
        $this->deleteJson("/api/v1/finance/loans/{$loan->id}")->assertForbidden();
    }

    public function test_repayments_update_summary_and_can_be_deleted(): void
    {
        $loan = Loan::factory()->create(['user_id' => $this->user->id, 'amount' => 500, 'amount_repaid' => 0, 'currency_code' => 'USD']);

        $repaymentId = $this->postJson("/api/v1/finance/loans/{$loan->id}/repayments", [
            'amount' => 125, 'repaid_at' => '2026-08-10', 'note' => 'Partial',
        ])->assertCreated()->json('data.id');

        $this->assertDatabaseHas('finance_loans', ['id' => $loan->id, 'amount_repaid' => 125, 'status' => 'partially_repaid']);
        $this->getJson('/api/v1/finance/loans-summary')->assertOk()->assertJsonPath('data.loan_receivables', 375);

        $this->deleteJson("/api/v1/finance/loans/{$loan->id}/repayments/{$repaymentId}")->assertOk();
        $this->assertDatabaseHas('finance_loans', ['id' => $loan->id, 'amount_repaid' => 0, 'status' => 'active']);
    }

    public function test_repayment_cannot_exceed_remaining_principal(): void
    {
        $loan = Loan::factory()->create(['user_id' => $this->user->id, 'amount' => 100, 'amount_repaid' => 0, 'currency_code' => 'USD']);

        $this->postJson("/api/v1/finance/loans/{$loan->id}/repayments", [
            'amount' => 101, 'repaid_at' => '2026-08-10',
        ])->assertUnprocessable()->assertJsonValidationErrors('amount');
    }

    public function test_repayment_must_belong_to_route_loan(): void
    {
        $loan = Loan::factory()->create(['user_id' => $this->user->id, 'currency_code' => 'USD']);
        $other = Loan::factory()->create(['user_id' => $this->user->id, 'currency_code' => 'USD']);
        $repayment = LoanRepayment::create(['loan_id' => $other->id, 'amount' => 1, 'repaid_at' => '2026-08-10']);

        $this->deleteJson("/api/v1/finance/loans/{$loan->id}/repayments/{$repayment->id}")->assertNotFound();
    }

    public function test_service_rechecks_remaining_principal_inside_transaction(): void
    {
        $loan = Loan::factory()->create(['user_id' => $this->user->id, 'amount' => 100, 'currency_code' => 'USD']);
        app(LoanService::class)->addRepayment($loan, ['amount' => 80, 'repaid_at' => '2026-08-10']);

        $this->expectException(ValidationException::class);
        app(LoanService::class)->addRepayment($loan, ['amount' => 30, 'repaid_at' => '2026-08-11']);
    }

    public function test_written_off_loan_rejects_repayment(): void
    {
        $loan = Loan::factory()->create(['user_id' => $this->user->id, 'amount' => 100, 'status' => 'written_off', 'currency_code' => 'USD']);

        $this->postJson("/api/v1/finance/loans/{$loan->id}/repayments", [
            'amount' => 10, 'repaid_at' => '2026-08-10',
        ])->assertUnprocessable()->assertJsonValidationErrors('amount');
    }

    public function test_currency_cannot_change_after_repayment(): void
    {
        Currency::create(['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'decimal_places' => 2]);
        $loan = Loan::factory()->create(['user_id' => $this->user->id, 'amount' => 100, 'amount_repaid' => 10, 'currency_code' => 'USD']);
        LoanRepayment::create(['loan_id' => $loan->id, 'amount' => 10, 'repaid_at' => '2026-08-10']);

        $this->patchJson("/api/v1/finance/loans/{$loan->id}", ['currency_code' => 'EUR'])
            ->assertUnprocessable()->assertJsonValidationErrors('currency_code');
    }

    public function test_partial_date_update_preserves_date_ordering(): void
    {
        $loan = Loan::factory()->create([
            'user_id' => $this->user->id, 'currency_code' => 'USD',
            'loan_date' => '2026-08-10', 'due_date' => '2026-08-20',
        ]);

        $this->patchJson("/api/v1/finance/loans/{$loan->id}", ['loan_date' => '2026-08-21'])
            ->assertUnprocessable()->assertJsonValidationErrors('due_date');
    }

    public function test_malformed_partial_dates_return_validation_errors(): void
    {
        $loan = Loan::factory()->create(['user_id' => $this->user->id, 'currency_code' => 'USD']);

        $this->patchJson("/api/v1/finance/loans/{$loan->id}", ['loan_date' => 'not-a-date'])
            ->assertUnprocessable()->assertJsonValidationErrors('loan_date');
        $this->patchJson("/api/v1/finance/loans/{$loan->id}", ['due_date' => 'not-a-date'])
            ->assertUnprocessable()->assertJsonValidationErrors('due_date');
    }

    public function test_service_rechecks_amount_against_current_repayments_on_update(): void
    {
        $loan = Loan::factory()->create(['user_id' => $this->user->id, 'amount' => 100, 'currency_code' => 'USD']);
        app(LoanService::class)->addRepayment($loan, ['amount' => 80, 'repaid_at' => '2026-08-10']);

        $this->expectException(ValidationException::class);
        app(LoanService::class)->updateLoan($loan, ['amount' => 50]);
    }
}
