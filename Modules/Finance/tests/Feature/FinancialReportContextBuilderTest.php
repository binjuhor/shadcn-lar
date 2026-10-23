<?php

namespace Modules\Finance\Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Finance\Models\{Account, Category, Currency, Transaction};
use Modules\Finance\Services\FinancialReportContextBuilder;
use Tests\TestCase;

class FinancialReportContextBuilderTest extends TestCase
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
            'name' => 'Business Checking',
            'account_type' => 'bank',
            'currency_code' => 'USD',
            'initial_balance' => 0,
            'current_balance' => 0,
            'is_active' => true,
        ]);

        $this->actingAs($this->user);
    }

    public function test_it_ranks_the_most_expensive_category_from_transactions(): void
    {
        $ads = $this->category('expense', 'Ads');
        $software = $this->category('expense', 'Software');

        $this->transaction('expense', 1500, '2026-01-05', $ads);
        $this->transaction('expense', 250, '2026-01-06', $software);

        $report = app(FinancialReportContextBuilder::class)
            ->buildReport($this->user->id, Carbon::parse('2026-07-04'));

        $this->assertSame('Ads', $report['top_expense_categories_ytd'][0]['category']);
        $this->assertSame(1500.0, $report['top_expense_categories_ytd'][0]['amount']);
    }

    public function test_it_builds_monthly_business_performance_for_may(): void
    {
        $sales = $this->category('income', 'Sales');
        $supplies = $this->category('expense', 'Supplies');

        $this->transaction('income', 5000, '2026-05-10', $sales);
        $this->transaction('expense', 1200, '2026-05-12', $supplies);
        $this->transaction('expense', 300, '2026-06-01', $supplies);

        $report = app(FinancialReportContextBuilder::class)
            ->buildReport($this->user->id, Carbon::parse('2026-07-04'));

        $may = collect($report['monthly'])->firstWhere('month', '2026-05');

        $this->assertSame(5000.0, $may['income']);
        $this->assertSame(1200.0, $may['expense']);
        $this->assertSame(3800.0, $may['net']);
    }

    public function test_it_builds_monthly_extremes_and_high_spend_signals(): void
    {
        $sales = $this->category('income', 'Sales');
        $supplies = $this->category('expense', 'Supplies');

        $this->transaction('income', 2000, '2026-01-10', $sales);
        $this->transaction('expense', 500, '2026-01-12', $supplies);
        $this->transaction('income', 7000, '2026-02-10', $sales);
        $this->transaction('expense', 1000, '2026-02-12', $supplies);
        $this->transaction('income', 4000, '2026-03-10', $sales);
        $this->transaction('expense', 5000, '2026-03-12', $supplies);

        $report = app(FinancialReportContextBuilder::class)
            ->buildReport($this->user->id, Carbon::parse('2026-07-04'));

        $insights = $report['monthly_insights'];

        $this->assertSame('2026-02', $insights['highest_income_month']['month']);
        $this->assertSame('2026-03', $insights['highest_expense_month']['month']);
        $this->assertSame('2026-03', $insights['lowest_net_month']['month']);
        $this->assertSame('2026-02', $insights['best_net_month']['month']);
        $this->assertSame(2166.67, $insights['average_monthly_expense']);
        $this->assertSame(['2026-03'], collect($insights['high_spend_months'])->pluck('month')->all());
    }

    public function test_it_estimates_year_end_from_year_to_date_daily_average(): void
    {
        $this->transaction('income', 1000, '2026-01-01');
        $this->transaction('expense', 400, '2026-01-02');

        $report = app(FinancialReportContextBuilder::class)
            ->buildReport($this->user->id, Carbon::parse('2026-01-10'));

        $this->assertSame(365, $report['year_end_projection']['elapsed_days'] + $report['year_end_projection']['remaining_days']);
        $this->assertSame(36500.0, $report['year_end_projection']['projected_income']);
        $this->assertSame(14600.0, $report['year_end_projection']['projected_expense']);
        $this->assertSame(21900.0, $report['year_end_projection']['projected_net']);
    }

    public function test_it_builds_chart_blocks_when_user_asks_for_chart_report(): void
    {
        $ads = $this->category('expense', 'Ads');
        $sales = $this->category('income', 'Sales');

        $this->transaction('income', 5000, '2026-05-10', $sales);
        $this->transaction('expense', 1200, '2026-05-12', $ads);

        $charts = app(FinancialReportContextBuilder::class)
            ->buildChartBlocksForQuestion($this->user->id, 'Show me a chart report of spending categories', Carbon::parse('2026-07-04'));

        $this->assertCount(2, $charts);
        $this->assertSame('Top expense categories', $charts[0]['title']);
        $this->assertSame(['amount' => 'Expenses'], $charts[0]['series']);
        $this->assertSame('Ads', $charts[0]['data'][0]['label']);
        $this->assertSame(1200.0, $charts[0]['data'][0]['amount']);
    }

    protected function category(string $type, string $name): Category
    {
        return Category::create([
            'name' => $name,
            'type' => $type,
            'color' => '#3b82f6',
        ]);
    }

    protected function transaction(string $type, float $amount, string $date, ?Category $category = null): Transaction
    {
        return Transaction::create([
            'account_id' => $this->account->id,
            'user_id' => $this->user->id,
            'category_id' => $category?->id,
            'transaction_type' => $type,
            'amount' => $amount,
            'currency_code' => 'USD',
            'transaction_date' => $date,
            'description' => "{$type} transaction",
        ]);
    }
}
