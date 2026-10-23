<?php

namespace Modules\Finance\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Modules\Finance\Models\{Account, Currency};
use Tests\TestCase;

class AccountIconTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        config(['media-library.disk_name' => 'public']);

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
            'name' => 'Test Account',
            'account_type' => 'bank',
            'currency_code' => 'VND',
            'initial_balance' => 0,
            'current_balance' => 0,
            'is_active' => true,
        ], $attributes));
    }

    public function test_api_exposes_icon_url_after_upload(): void
    {
        Sanctum::actingAs($this->user);
        $account = $this->createAccount();

        $response = $this->put("/api/v1/finance/accounts/{$account->id}", [
            'icon' => UploadedFile::fake()->image('icon.png', 64, 64),
        ]);

        $response->assertOk();

        $this->assertNotNull($response->json('data.icon_url'));
        $this->assertStringContainsString('icon', $response->json('data.icon_url'));

        $account->refresh();
        $this->assertCount(1, $account->getMedia('icon'));
    }

    public function test_svg_icon_is_sanitized_before_storage(): void
    {
        Sanctum::actingAs($this->user);
        $account = $this->createAccount();

        $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10">
  <script>alert('xss')</script>
  <rect width="10" height="10" onload="alert('xss')" />
</svg>
SVG;

        $file = UploadedFile::fake()->createWithContent('icon.svg', $svg);

        $response = $this->put("/api/v1/finance/accounts/{$account->id}", [
            'icon' => $file,
        ]);

        $response->assertOk();

        $account->refresh();
        $media = $account->getFirstMedia('icon');
        $this->assertNotNull($media);

        $stored = file_get_contents($media->getPath());
        $this->assertStringNotContainsString('<script', $stored);
        $this->assertStringNotContainsString('onload', $stored);
    }

    public function test_user_can_remove_account_icon(): void
    {
        Sanctum::actingAs($this->user);
        $account = $this->createAccount();

        $this->put("/api/v1/finance/accounts/{$account->id}", [
            'icon' => UploadedFile::fake()->image('icon.png', 64, 64),
        ])->assertOk();

        $account->refresh();
        $this->assertCount(1, $account->getMedia('icon'));

        $this->deleteJson("/api/v1/finance/accounts/{$account->id}/icon")
            ->assertOk();

        $account->refresh();
        $this->assertCount(0, $account->getMedia('icon'));
    }

    public function test_user_cannot_upload_icon_to_another_users_account(): void
    {
        $other = User::factory()->create();
        $account = $this->createAccount(['user_id' => $other->id]);

        Sanctum::actingAs($this->user);

        $this->put("/api/v1/finance/accounts/{$account->id}", [
            'icon' => UploadedFile::fake()->image('icon.png', 64, 64),
        ])->assertForbidden();

        $this->assertCount(0, $account->refresh()->getMedia('icon'));
    }
}
