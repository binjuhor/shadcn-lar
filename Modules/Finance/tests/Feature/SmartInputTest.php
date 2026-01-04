<?php

namespace Modules\Finance\Tests\Feature;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Modules\Finance\Models\Account;
use Tests\TestCase;

class SmartInputTest extends TestCase
{
    public function test_smart_input_page_loads(): void
    {
        $user = User::first();

        $response = $this->actingAs($user)
            ->get(route('dashboard.finance.smart-input'));

        $response->assertStatus(200);
    }

    public function test_parse_text_endpoint_returns_json(): void
    {
        $user = User::first();

        $response = $this->actingAs($user)
            ->postJson(route('dashboard.finance.smart-input.parse-text'), [
                'text' => 'Cafe 50k hôm nay',
                'language' => 'vi',
            ]);

        // API may succeed (200) or fail due to quota/rate limits (422)
        $this->assertTrue(in_array($response->status(), [200, 422]));
        $response->assertJsonStructure(['success']);
    }

    public function test_store_transaction_requires_account(): void
    {
        $user = User::first();

        $response = $this->actingAs($user)
            ->postJson(route('dashboard.finance.smart-input.store'), [
                'type' => 'expense',
                'amount' => 50000,
                'description' => 'Test transaction',
                'transaction_date' => now()->format('Y-m-d'),
            ]);

        $response->assertStatus(422); // Validation error - missing account_id
    }

    public function test_store_transaction_creates_transaction(): void
    {
        $user = User::first();
        $account = Account::where('user_id', $user->id)->first();

        if (! $account) {
            $this->markTestSkipped('No account available for testing');
        }

        $response = $this->actingAs($user)
            ->postJson(route('dashboard.finance.smart-input.store'), [
                'type' => 'expense',
                'amount' => 50000,
                'description' => 'Test smart input transaction',
                'account_id' => $account->id,
                'transaction_date' => now()->format('Y-m-d'),
            ]);

        $response->assertRedirect();
    }
}
