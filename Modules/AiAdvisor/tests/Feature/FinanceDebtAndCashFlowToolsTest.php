<?php

namespace Modules\AiAdvisor\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\AiAdvisor\DTOs\{ToolCall, ToolContext, ToolResult};
use Modules\AiAdvisor\Services\ToolExecutor;
use Modules\Finance\Models\{Account, Borrowing, Category, Currency, Loan, Transaction};
use Modules\Finance\Services\LoanService;
use Modules\Finance\Services\TransactionService;
use Tests\TestCase;

class FinanceDebtAndCashFlowToolsTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private User $other;

    protected function setUp(): void
    {
        parent::setUp();
        config(['aiadvisor.mutations_enabled' => true]);
        Currency::create(['code' => 'VND', 'name' => 'Vietnamese Dong', 'symbol' => '₫', 'decimal_places' => 0, 'is_active' => true, 'is_default' => true]);
        $this->actor = User::factory()->create();
        $this->other = User::factory()->create();
    }

    public function test_debt_summary_groups_by_person_flags_overdue_and_ignores_other_users(): void
    {
        $this->loan($this->actor, 'Nam', 2_000_000, ['due_date' => now()->subDay()->toDateString()]);
        $this->loan($this->actor, 'nam ', 1_000_000);
        $this->loan($this->actor, 'Lan', 500_000, ['status' => 'repaid', 'amount_repaid' => 500_000]);
        $this->loan($this->other, 'Nam', 9_000_000);
        Borrowing::create(['user_id' => $this->actor->id, 'lender_name' => 'Mom', 'amount' => 1_500_000, 'amount_repaid' => 0, 'currency_code' => 'VND', 'borrowed_date' => now()->toDateString(), 'status' => 'active']);

        $summary = $this->tool('getDebtSummary')->data;

        $this->assertSame(2, $summary['owed_to_me']['count']);
        $this->assertSame(3_000_000.0, $summary['owed_to_me']['by_currency'][0]['outstanding']);
        $this->assertCount(1, $summary['owed_to_me']['by_person']);
        $this->assertCount(1, $summary['owed_to_me']['overdue']);
        $this->assertSame(1_500_000.0, $summary['i_owe']['by_currency'][0]['outstanding']);
        $this->assertSame(1_500_000.0, $summary['net_position']['net']);
    }

    public function test_get_loans_filters_by_counterparty_for_the_actor_only(): void
    {
        $this->loan($this->actor, 'Nguyễn Nam', 100);
        $this->loan($this->actor, 'Lan', 100);
        $this->loan($this->other, 'Nam', 100);

        $items = $this->tool('getLoans', ['counterparty' => 'Nam'])->data['items'];

        $this->assertSame(['Nguyễn Nam'], array_column($items, 'person'));
    }

    public function test_create_loan_requires_confirmation_then_records_loan_and_expense(): void
    {
        $account = $this->account($this->actor, 'Techcombank', 5_000_000);
        $args = ['borrower_name' => 'Nam', 'amount' => 2_000_000, 'loan_date' => '2026-10-12', 'account_id' => $account->id];

        $pending = $this->tool('createLoan', $args);

        $this->assertSame('confirmation_required', $pending->errorCode);
        $this->assertSame('Techcombank', $pending->meta['preview']['account']);
        $this->assertSame(0, Loan::count());

        $result = $this->confirm('createLoan', $args, $pending);

        $this->assertTrue($result->success);
        $loan = Loan::firstOrFail();
        $this->assertSame('Nam', $loan->borrower_name);
        $this->assertSame('VND', $loan->currency_code);
        $this->assertNotNull($loan->transaction_id);
        $this->assertSame('expense', Transaction::find($loan->transaction_id)->transaction_type);
        $this->assertSame(3_000_000.0, (float) $account->fresh()->current_balance);
    }

    public function test_loan_without_account_does_not_touch_balances(): void
    {
        $this->confirmed('createLoan', ['borrower_name' => 'Nam', 'amount' => 100, 'loan_date' => '2026-10-12']);

        $this->assertSame(1, Loan::count());
        $this->assertSame(0, Transaction::count());
    }

    public function test_over_repayment_is_rejected_and_rolls_back_the_ledger_entry(): void
    {
        $account = $this->account($this->actor, 'Cash', 0);
        $loan = $this->loan($this->actor, 'Nam', 1_000);

        $result = $this->confirmed('recordLoanRepayment', ['loan_id' => $loan->id, 'amount' => 5_000, 'account_id' => $account->id]);

        $this->assertFalse($result->success);
        $this->assertSame(0, Transaction::count());
        $this->assertSame(0.0, (float) $account->fresh()->current_balance);
        $this->assertSame('active', $loan->fresh()->status);
    }

    public function test_borrowing_repayment_with_account_records_expense_and_updates_status(): void
    {
        $account = $this->account($this->actor, 'Momo', 2_000);
        $borrowing = Borrowing::create(['user_id' => $this->actor->id, 'lender_name' => 'Lan', 'amount' => 1_500, 'amount_repaid' => 0, 'currency_code' => 'VND', 'borrowed_date' => '2026-10-01', 'status' => 'active']);

        $result = $this->confirmed('recordBorrowingRepayment', ['borrowing_id' => $borrowing->id, 'amount' => 500, 'account_id' => $account->id]);

        $this->assertTrue($result->success);
        $this->assertSame('partially_repaid', $borrowing->fresh()->status);
        $this->assertSame(1_500.0, (float) $account->fresh()->current_balance);
    }

    public function test_deleting_a_repayment_reverses_its_linked_ledger_entry(): void
    {
        $account = $this->account($this->actor, 'Cash', 0);
        $loan = $this->loan($this->actor, 'Nam', 1_000);
        $this->confirmed('recordLoanRepayment', ['loan_id' => $loan->id, 'amount' => 400, 'account_id' => $account->id]);
        $repayment = $loan->repayments()->firstOrFail();

        $this->assertNotNull($repayment->transaction_id);
        $this->assertSame(400.0, (float) $account->fresh()->current_balance);

        app(LoanService::class)->deleteRepayment($repayment);

        $this->assertSame(0.0, (float) $account->fresh()->current_balance);
        $this->assertSame(0, Transaction::count());
        $this->assertSame('active', $loan->fresh()->status);
    }

    public function test_unknown_currency_and_overlong_cash_flow_period_are_rejected(): void
    {
        $loan = $this->tool('createLoan', ['borrower_name' => 'Nam', 'amount' => 1, 'loan_date' => '2026-10-12', 'currency_code' => 'XYZ']);
        $period = $this->tool('getCashFlowBreakdown', ['start_date' => '0001-01-01', 'end_date' => '2026-10-12']);

        $this->assertSame('validation_failed', $loan->errorCode);
        $this->assertFalse($period->success);
    }

    public function test_cross_currency_transfer_without_rate_returns_a_safe_validation_error(): void
    {
        Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'is_active' => true]);
        $from = $this->account($this->actor, 'VCB', 5_000);
        $to = Account::create(['user_id' => $this->actor->id, 'name' => 'Wise', 'account_type' => 'bank', 'currency_code' => 'USD', 'initial_balance' => 0, 'current_balance' => 0, 'is_active' => true]);

        $result = $this->confirmed('createTransfer', ['from_account_id' => $from->id, 'to_account_id' => $to->id, 'amount' => 1_000, 'transaction_date' => '2026-10-12']);

        $this->assertSame('validation_failed', $result->errorCode);
        $this->assertSame(5_000.0, (float) $from->fresh()->current_balance);
    }

    public function test_cannot_repay_or_write_off_another_users_loan(): void
    {
        $loan = $this->loan($this->other, 'Nam', 1_000);

        $this->assertFalse($this->confirmed('recordLoanRepayment', ['loan_id' => $loan->id, 'amount' => 100])->success);
        $this->assertFalse($this->confirmed('writeOffLoan', ['loan_id' => $loan->id])->success);
        $this->assertSame('active', $loan->fresh()->status);
    }

    public function test_write_off_marks_outstanding_loan(): void
    {
        $loan = $this->loan($this->actor, 'Nam', 1_000);

        $this->assertTrue($this->confirmed('writeOffLoan', ['loan_id' => $loan->id])->success);
        $this->assertSame('written_off', $loan->fresh()->status);
    }

    public function test_transfer_moves_money_between_own_accounts_only(): void
    {
        $from = $this->account($this->actor, 'VCB', 5_000);
        $to = $this->account($this->actor, 'Momo', 0);
        $foreign = $this->account($this->other, 'Theirs', 0);

        $result = $this->confirmed('createTransfer', ['from_account_id' => $from->id, 'to_account_id' => $to->id, 'amount' => 2_000, 'transaction_date' => '2026-10-12']);

        $this->assertTrue($result->success);
        $this->assertSame(3_000.0, (float) $from->fresh()->current_balance);
        $this->assertSame(2_000.0, (float) $to->fresh()->current_balance);

        $blocked = $this->confirmed('createTransfer', ['from_account_id' => $from->id, 'to_account_id' => $foreign->id, 'amount' => 1, 'transaction_date' => '2026-10-12']);
        $this->assertFalse($blocked->success);
        $this->assertSame(0.0, (float) $foreign->fresh()->current_balance);
    }

    public function test_preview_never_resolves_another_users_record_names(): void
    {
        $foreign = $this->account($this->other, 'Secret Bank', 0);

        $pending = $this->tool('createLoan', ['borrower_name' => 'Nam', 'amount' => 1, 'loan_date' => '2026-10-12', 'account_id' => $foreign->id]);

        $this->assertSame($foreign->id, $pending->meta['preview']['account_id']);
        $this->assertStringNotContainsString('Secret Bank', json_encode($pending->meta['preview']));
    }

    public function test_cash_flow_breakdown_splits_passive_and_active_income_and_skips_transfers(): void
    {
        $account = $this->account($this->actor, 'Bank', 0);
        $other = $this->account($this->actor, 'Wallet', 0);
        $rent = Category::create(['name' => 'Rent income', 'type' => 'income', 'is_passive' => true, 'is_active' => true, 'user_id' => $this->actor->id]);
        $salary = Category::create(['name' => 'Salary', 'type' => 'income', 'is_active' => true, 'user_id' => $this->actor->id]);
        $food = Category::create(['name' => 'Food', 'type' => 'expense', 'expense_type' => 'essential', 'is_active' => true, 'user_id' => $this->actor->id]);
        $today = now()->toDateString();
        $this->transaction($account, 'income', 5_000_000, $rent->id, $today);
        $this->transaction($account, 'income', 20_000_000, $salary->id, $today);
        $this->transaction($account, 'expense', 10_000_000, $food->id, $today);
        app(TransactionService::class)->recordTransfer(['user_id' => $this->actor->id, 'from_account_id' => $account->id, 'to_account_id' => $other->id, 'amount' => 1_000_000, 'transaction_date' => $today]);

        $result = $this->tool('getCashFlowBreakdown')->data;

        $this->assertSame(5_000_000.0, $result['totals']['passive_income']);
        $this->assertSame(20_000_000.0, $result['totals']['active_income']);
        $this->assertSame(10_000_000.0, $result['totals']['essential_expense']);
        $this->assertSame(10_000_000.0, $result['totals']['expense']);
        $this->assertSame(50.0, $result['averages']['coverage']);
        $this->assertSame('Rent income', $result['passive_income_categories'][0]['category']);
        $this->assertArrayNotHasKey('monthly_data', $result);
    }

    private function tool(string $tool, array $args = []): ToolResult
    {
        return app(ToolExecutor::class)->execute($this->context(), new ToolCall((string) Str::uuid(), $tool, $args));
    }

    private function confirm(string $tool, array $args, ToolResult $pending): ToolResult
    {
        return app(ToolExecutor::class)->execute(
            $this->context(),
            new ToolCall((string) Str::uuid(), $tool, $args, true, $pending->meta['confirmation_token'], (string) Str::uuid()),
        );
    }

    private function confirmed(string $tool, array $args): ToolResult
    {
        return $this->confirm($tool, $args, $this->tool($tool, $args));
    }

    private function context(): ToolContext
    {
        return new ToolContext($this->actor, 'correlation-debt', 7);
    }

    private function account(User $user, string $name, int $balance): Account
    {
        return Account::create(['user_id' => $user->id, 'name' => $name, 'account_type' => 'bank', 'currency_code' => 'VND', 'initial_balance' => $balance, 'current_balance' => $balance, 'is_active' => true, 'exclude_from_total' => false]);
    }

    private function loan(User $user, string $name, int $amount, array $extra = []): Loan
    {
        return Loan::create($extra + ['user_id' => $user->id, 'borrower_name' => $name, 'amount' => $amount, 'amount_repaid' => 0, 'currency_code' => 'VND', 'loan_date' => now()->toDateString(), 'status' => 'active']);
    }

    private function transaction(Account $account, string $type, int $amount, int $categoryId, string $date): void
    {
        Transaction::create(['user_id' => $account->user_id, 'account_id' => $account->id, 'category_id' => $categoryId, 'transaction_type' => $type, 'amount' => $amount, 'currency_code' => 'VND', 'transaction_date' => $date]);
    }
}
