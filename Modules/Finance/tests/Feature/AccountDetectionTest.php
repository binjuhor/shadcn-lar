<?php

namespace Modules\Finance\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Finance\Models\{Account, Category, Currency, Transaction};
use Modules\Finance\Services\AccountDetectionService;
use Tests\TestCase;

class AccountDetectionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        Currency::create([
            'code' => 'VND',
            'name' => 'Vietnamese Dong',
            'symbol' => '₫',
            'decimal_places' => 0,
            'is_default' => true,
        ]);
    }

    protected function createAccount(array $attributes = []): Account
    {
        return Account::create(array_merge([
            'user_id' => $this->user->id,
            'name' => 'Account',
            'account_type' => 'bank',
            'currency_code' => 'VND',
            'initial_balance' => 0,
            'current_balance' => 0,
            'is_active' => true,
        ], $attributes));
    }

    protected function detect(array $overrides = []): ?array
    {
        return app(AccountDetectionService::class)->detect(
            $this->user->id,
            $overrides['rawText'] ?? null,
            $overrides['hint'] ?? null,
            $overrides['description'] ?? null,
            $overrides['categoryId'] ?? null,
        );
    }

    public function test_detects_account_from_account_number_in_raw_text(): void
    {
        $card = $this->createAccount([
            'name' => 'Everyday Card',
            'account_type' => 'credit_card',
            'account_number' => '19001234567890',
        ]);
        $this->createAccount(['name' => 'Savings']);

        $match = $this->detect(['rawText' => 'TK *7890 thanh toan 500k']);

        $this->assertSame($card->id, $match['id']);
        $this->assertSame(AccountDetectionService::REASON_ACCOUNT_NUMBER, $match['reason']);
    }

    public function test_account_number_does_not_match_inside_a_longer_number(): void
    {
        $this->createAccount([
            'name' => 'Short Number',
            'account_number' => '1234',
        ]);
        $fallback = $this->createAccount(['name' => 'Fallback', 'is_default_payment' => true]);

        $match = $this->detect(['rawText' => 'Total 123456 VND']);

        $this->assertSame($fallback->id, $match['id']);
    }

    public function test_detects_account_from_institution_name_in_raw_text(): void
    {
        $techcombank = $this->createAccount([
            'name' => 'Credit Card',
            'institution_name' => 'Techcombank',
        ]);
        $this->createAccount(['name' => 'Other Bank', 'institution_name' => 'Vietcombank']);

        $match = $this->detect(['rawText' => 'Techcombank card payment 200k']);

        $this->assertSame($techcombank->id, $match['id']);
        $this->assertSame(AccountDetectionService::REASON_NAME, $match['reason']);
    }

    public function test_detects_account_from_explicit_hint(): void
    {
        $wallet = $this->createAccount(['name' => 'MoMo Wallet', 'account_type' => 'e_wallet']);
        $this->createAccount(['name' => 'Bank Account']);

        $match = $this->detect(['hint' => 'MoMo']);

        $this->assertSame($wallet->id, $match['id']);
        $this->assertSame(AccountDetectionService::REASON_HINT, $match['reason']);
    }

    public function test_learns_account_from_similar_transaction_description(): void
    {
        $card = $this->createAccount(['name' => 'Coffee Card']);
        $other = $this->createAccount(['name' => 'Other']);

        $this->createTransaction($card, ['description' => 'Highland Coffee District 1']);
        $this->createTransaction($other, ['description' => 'Grab ride to office']);

        $match = $this->detect(['description' => 'Highland Coffee']);

        $this->assertSame($card->id, $match['id']);
        $this->assertSame(AccountDetectionService::REASON_HISTORY_DESCRIPTION, $match['reason']);
    }

    public function test_learns_account_from_category_history(): void
    {
        $card = $this->createAccount(['name' => 'Card']);
        $cash = $this->createAccount(['name' => 'Cash']);

        $category = Category::create([
            'user_id' => $this->user->id,
            'name' => 'Groceries',
            'type' => 'expense',
            'is_active' => true,
        ]);

        $this->createTransaction($card, ['category_id' => $category->id, 'description' => 'Market']);
        $this->createTransaction($card, ['category_id' => $category->id, 'description' => 'Supermarket']);
        $this->createTransaction($cash, ['category_id' => $category->id, 'description' => 'Snacks']);

        $match = $this->detect(['categoryId' => $category->id]);

        $this->assertSame($card->id, $match['id']);
        $this->assertSame(AccountDetectionService::REASON_HISTORY_CATEGORY, $match['reason']);
    }

    public function test_falls_back_to_configured_default(): void
    {
        $configured = $this->createAccount(['name' => 'Configured']);
        $this->createAccount(['name' => 'Other', 'is_default_payment' => true]);

        $this->user->update([
            'finance_settings' => ['default_smart_input_account_id' => $configured->id],
        ]);

        $match = $this->detect();

        $this->assertSame($configured->id, $match['id']);
        $this->assertSame(AccountDetectionService::REASON_CONFIGURED_DEFAULT, $match['reason']);
    }

    public function test_falls_back_to_default_payment_account(): void
    {
        $this->createAccount(['name' => 'Regular']);
        $default = $this->createAccount(['name' => 'Default Payment', 'is_default_payment' => true]);

        $match = $this->detect();

        $this->assertSame($default->id, $match['id']);
        $this->assertSame(AccountDetectionService::REASON_DEFAULT_PAYMENT, $match['reason']);
    }

    public function test_returns_null_when_user_has_no_active_accounts(): void
    {
        $this->assertNull($this->detect());
    }

    protected function createTransaction(Account $account, array $attributes = []): Transaction
    {
        return Transaction::create(array_merge([
            'user_id' => $this->user->id,
            'account_id' => $account->id,
            'transaction_type' => 'expense',
            'amount' => 50000,
            'description' => 'Transaction',
            'transaction_date' => now()->subDay()->format('Y-m-d'),
            'currency_code' => 'VND',
        ], $attributes));
    }
}
