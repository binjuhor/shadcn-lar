<?php

namespace Modules\Finance\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Finance\Models\{Borrowing, Currency};
use Modules\Finance\Services\BorrowingPayableService;
use Tests\TestCase;

class BorrowingPayableServiceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'is_default' => true]);
    }

    public function test_it_sums_only_owned_outstanding_borrowings(): void
    {
        Borrowing::factory()->create(['user_id' => $this->user->id, 'amount' => 500, 'amount_repaid' => 125, 'status' => 'partially_repaid', 'currency_code' => 'USD']);
        Borrowing::factory()->create(['user_id' => $this->user->id, 'amount' => 400, 'amount_repaid' => 0, 'status' => 'active', 'currency_code' => 'USD']);
        Borrowing::factory()->create(['user_id' => $this->user->id, 'amount' => 900, 'amount_repaid' => 900, 'status' => 'repaid', 'currency_code' => 'USD']);
        Borrowing::factory()->create(['user_id' => $this->user->id, 'amount' => 800, 'status' => 'written_off', 'currency_code' => 'USD']);
        Borrowing::factory()->create(['user_id' => User::factory()->create()->id, 'amount' => 700, 'currency_code' => 'USD']);

        $deleted = Borrowing::factory()->create(['user_id' => $this->user->id, 'amount' => 300, 'currency_code' => 'USD']);
        $deleted->delete();

        $total = app(BorrowingPayableService::class)->totalForUser($this->user);

        $this->assertSame(775.0, $total);
    }
}
