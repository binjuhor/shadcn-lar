<?php

namespace Modules\Finance\Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Finance\Models\{Currency, Loan};
use Modules\Finance\Services\FinancialLoanContextBuilder;
use Tests\TestCase;

class FinancialLoanContextBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-07-07'));

        $this->user = User::factory()->create();

        Currency::create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'decimal_places' => 2,
            'is_default' => true,
        ]);

        $this->actingAs($this->user);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_reports_outstanding_overdue_and_upcoming_loan_receivables(): void
    {
        $overdue = $this->loan([
            'borrower_name' => 'Alex',
            'amount' => 1000,
            'amount_repaid' => 250,
            'loan_date' => '2026-05-01',
            'due_date' => '2026-07-01',
            'status' => 'partially_repaid',
        ]);
        $overdue->repayments()->create([
            'amount' => 250,
            'repaid_at' => '2026-06-10',
        ]);

        $this->loan([
            'borrower_name' => 'Mina',
            'amount' => 500,
            'amount_repaid' => 0,
            'loan_date' => '2026-06-01',
            'due_date' => '2026-07-20',
            'status' => 'active',
        ]);

        $this->loan([
            'borrower_name' => 'Written Off',
            'amount' => 300,
            'amount_repaid' => 0,
            'loan_date' => '2026-04-01',
            'status' => 'written_off',
        ]);

        $context = app(FinancialLoanContextBuilder::class)->build($this->user->id);

        $this->assertStringContainsString('Loan Receivables Report', $context);
        $this->assertStringContainsString('Total loaned (excluding written off): 1500 USD', $context);
        $this->assertStringContainsString('Total repaid: 250 USD', $context);
        $this->assertStringContainsString('Total outstanding/expected back: 1250 USD', $context);
        $this->assertStringContainsString('Alex (partially_repaid); loaned 1000 USD; repaid 250 USD; remaining 750 USD', $context);
        $this->assertStringContainsString('due 2026-07-01 (6 days overdue)', $context);
        $this->assertStringContainsString('Mina (active); loaned 500 USD; repaid 0 USD; remaining 500 USD', $context);
        $this->assertStringContainsString('due 2026-07-20 (13 days left)', $context);
        $this->assertStringContainsString('Written off loans:', $context);
    }

    protected function loan(array $attributes = []): Loan
    {
        return Loan::create(array_merge([
            'user_id' => $this->user->id,
            'borrower_name' => 'Borrower',
            'amount' => 100,
            'amount_repaid' => 0,
            'currency_code' => 'USD',
            'loan_date' => '2026-01-01',
            'due_date' => null,
            'status' => 'active',
        ], $attributes));
    }
}
