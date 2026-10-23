<?php

namespace Modules\Finance\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\Currency;
use Modules\Finance\Services\BankIconLibrary;
use Tests\TestCase;

class BankIconLibraryTest extends TestCase
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

    public function test_library_lists_bundled_icons(): void
    {
        $library = app(BankIconLibrary::class);

        $items = $library->list();

        $this->assertNotEmpty($items);

        $keys = array_column($items, 'key');
        $this->assertContains('vietcom-bank.svg', $keys);

        $vietcom = $items[array_search('vietcom-bank.svg', $keys)];
        $this->assertSame('vietcom bank', $vietcom['name']);
        $this->assertStringContainsString('/account-icons/', $vietcom['url']);
    }

    public function test_library_path_rejects_path_traversal(): void
    {
        $library = app(BankIconLibrary::class);

        $this->assertNull($library->path('../../.env'));
        $this->assertNull($library->path('nope.svg'));
        $this->assertNull($library->path('vietcom-bank.php'));
        $this->assertNotNull($library->path('vietcom-bank.svg'));
    }

    public function test_store_account_with_bank_icon(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/finance/accounts', [
            'name' => 'Vietcombank',
            'account_type' => 'bank',
            'currency_code' => 'VND',
            'initial_balance' => 0,
            'bank_icon' => 'vietcom-bank.svg',
        ]);

        $response->assertCreated();
        $this->assertNotNull($response->json('data.icon_url'));

        $account = $response->json('data');
        $model = Account::find($account['id']);
        $this->assertNotNull($model);
        $this->assertCount(1, $model->getMedia('icon'));

        $stored = file_get_contents($model->getFirstMedia('icon')->getPath());
        $this->assertStringNotContainsString('<script', $stored);
    }

    public function test_store_account_with_unknown_bank_icon_ignored(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/finance/accounts', [
            'name' => 'Unknown',
            'account_type' => 'bank',
            'currency_code' => 'VND',
            'initial_balance' => 0,
            'bank_icon' => '../../.env',
        ]);

        $response->assertCreated();
        $this->assertNull($response->json('data.icon_url'));

        $model = Account::find($response->json('data.id'));
        $this->assertCount(0, $model->getMedia('icon'));
    }

    public function test_api_lists_bank_icons(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/finance/account-icons');

        $response->assertOk();

        $keys = array_column($response->json('data'), 'key');
        $this->assertContains('vietcom-bank.svg', $keys);

        $url = $response->json('data')[array_search('vietcom-bank.svg', $keys)]['url'];
        $this->assertStringContainsString('/api/v1/finance/account-icons/', $url);
    }

    public function test_api_serves_bank_icon(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->get('/api/v1/finance/account-icons/vietcom-bank.svg');

        $response->assertOk();
        $this->assertStringContainsString('image/svg+xml', $response->headers->get('Content-Type'));
    }

    public function test_api_bank_icons_requires_authentication(): void
    {
        $this->getJson('/api/v1/finance/account-icons')->assertUnauthorized();
        $this->getJson('/api/v1/finance/account-icons/vietcom-bank.svg')->assertUnauthorized();
    }
}
