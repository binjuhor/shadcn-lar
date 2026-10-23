<?php

namespace Modules\Finance\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Finance\Models\{Account, Currency, SmartInputHistory, Transaction};
use Tests\TestCase;

class SmartInputTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithAccount(): array
    {
        $user = User::factory()->create();

        Currency::create([
            'code' => 'VND',
            'name' => 'Vietnamese Dong',
            'symbol' => '₫',
            'decimal_places' => 0,
            'is_default' => true,
        ]);

        $account = Account::create([
            'user_id' => $user->id,
            'name' => 'Test Account',
            'account_type' => 'bank',
            'currency_code' => 'VND',
            'initial_balance' => 1000000,
            'current_balance' => 1000000,
            'is_active' => true,
        ]);

        return [$user, $account];
    }

    public function test_old_smart_input_and_advisor_pages_redirect_to_assistant(): void
    {
        [$user] = $this->createUserWithAccount();

        $this->actingAs($user)->get('/dashboard/finance/smart-input')->assertRedirect('/dashboard/finance/assistant');
        $this->actingAs($user)->get('/dashboard/finance/advisor')->assertRedirect('/dashboard/finance/assistant');
    }

    public function test_api_store_transaction_requires_account(): void
    {
        [$user] = $this->createUserWithAccount();

        $this->actingAs($user)->postJson('/api/v1/finance/smart-input/transactions', [
            'type' => 'expense',
            'amount' => 50000,
            'description' => 'Test transaction',
            'transaction_date' => now()->format('Y-m-d'),
        ])->assertStatus(422);
    }

    public function test_api_store_transaction_creates_transaction(): void
    {
        [$user, $account] = $this->createUserWithAccount();

        $this->actingAs($user)->postJson('/api/v1/finance/smart-input/transactions', [
            'type' => 'expense',
            'amount' => 50000,
            'description' => 'Test smart input transaction',
            'account_id' => $account->id,
            'transaction_date' => now()->format('Y-m-d'),
        ])->assertCreated();

        $this->assertSame(1, Transaction::where('account_id', $account->id)->count());
    }

    public function test_quick_entry_api_preserves_sub_unit_decimal_amount(): void
    {
        $user = User::factory()->create();
        Currency::create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'decimal_places' => 2,
            'is_default' => true,
        ]);
        $account = Account::create([
            'user_id' => $user->id,
            'name' => 'USD Account',
            'account_type' => 'bank',
            'currency_code' => 'USD',
            'initial_balance' => 10,
            'current_balance' => 10,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->postJson(route('api.api.finance.smart-input.quick-entry'), [
                'type' => 'expense',
                'amount' => 0.67,
                'description' => 'Small purchase',
                'account_id' => $account->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.amount', 0.67);

        $this->assertDatabaseHas('finance_transactions', [
            'user_id' => $user->id,
            'amount' => 0.67,
            'currency_code' => 'USD',
        ]);
    }

    public function test_api_numeric_text_creates_history_and_returns_history_id(): void
    {
        [$user, $account] = $this->createUserWithAccount();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/finance/smart-input/parse-text', [
                'text' => '50000',
                'language' => 'vi',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.amount', 50000)
            ->assertJsonPath('data.suggested_account.id', $account->id)
            ->assertJsonStructure(['history_id']);

        $this->assertDatabaseHas('finance_smart_input_histories', [
            'id' => $response->json('history_id'),
            'user_id' => $user->id,
            'input_type' => 'text',
            'raw_text' => '50000',
        ]);
    }

    public function test_api_history_is_scoped_filtered_and_cursor_paginated(): void
    {
        [$user] = $this->createUserWithAccount();
        $otherUser = User::factory()->create();

        $older = SmartInputHistory::create([
            'user_id' => $user->id,
            'input_type' => 'text',
            'raw_text' => 'older',
            'parsed_result' => ['amount' => 100],
            'language' => 'en',
        ]);
        $newer = SmartInputHistory::create([
            'user_id' => $user->id,
            'input_type' => 'image',
            'parsed_result' => ['amount' => 200],
            'language' => 'en',
        ]);
        SmartInputHistory::create([
            'user_id' => $otherUser->id,
            'input_type' => 'image',
            'parsed_result' => ['amount' => 300],
            'language' => 'en',
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/finance/smart-input/history?limit=1')
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $newer->id)
            ->assertJsonPath('data.has_more', true)
            ->assertJsonPath('data.next_before_id', $newer->id);

        $this->actingAs($user)
            ->getJson("/api/v1/finance/smart-input/history?input_type=text&before_id={$newer->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $older->id);
    }

    public function test_api_saves_history_corrections_and_copies_attachment_to_transaction(): void
    {
        Storage::fake('public');
        config(['media-library.disk_name' => 'public']);

        [$user, $account] = $this->createUserWithAccount();
        $history = SmartInputHistory::create([
            'user_id' => $user->id,
            'input_type' => 'image',
            'parsed_result' => ['amount' => 100, 'description' => 'Original'],
            'language' => 'en',
        ]);
        $history->addMedia(UploadedFile::fake()->image('receipt.jpg'))
            ->toMediaCollection('input_attachments');

        $response = $this->actingAs($user)
            ->postJson('/api/v1/finance/smart-input/transactions', [
                'type' => 'expense',
                'amount' => 250,
                'description' => 'Corrected',
                'account_id' => $account->id,
                'transaction_date' => '2026-08-04',
                'history_id' => $history->id,
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.history_id', $history->id);

        $transaction = Transaction::findOrFail($response->json('data.id'));
        $history->refresh();

        $this->assertTrue($history->transaction_saved);
        $this->assertSame($transaction->id, $history->transaction_id);
        $this->assertSame('Corrected', $history->parsed_result['description']);
        $this->assertCount(1, $transaction->getMedia('bills'));
    }

    public function test_api_cannot_link_or_delete_another_users_history(): void
    {
        [$user, $account] = $this->createUserWithAccount();
        $otherUser = User::factory()->create();
        $history = SmartInputHistory::create([
            'user_id' => $otherUser->id,
            'input_type' => 'text',
            'parsed_result' => ['amount' => 100],
            'language' => 'en',
        ]);

        $this->actingAs($user)
            ->postJson('/api/v1/finance/smart-input/transactions', [
                'type' => 'expense',
                'amount' => 100,
                'description' => 'Not allowed',
                'account_id' => $account->id,
                'transaction_date' => '2026-08-04',
                'history_id' => $history->id,
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount('finance_transactions', 0);

        $this->actingAs($user)
            ->deleteJson("/api/v1/finance/smart-input/history/{$history->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('finance_smart_input_histories', ['id' => $history->id]);
    }
}
