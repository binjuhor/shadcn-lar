<?php

namespace Modules\Finance\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Finance\Models\{Category, Currency};
use Tests\TestCase;

class CategoryApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

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
    }

    protected function createCategory(array $attributes = []): Category
    {
        return Category::create(array_merge([
            'user_id' => $this->user->id,
            'name' => 'Test Category',
            'type' => 'expense',
            'color' => '#ff0000',
            'icon' => 'shopping-cart',
            'is_active' => true,
        ], $attributes));
    }

    public function test_user_can_list_categories(): void
    {
        Sanctum::actingAs($this->user);

        $this->createCategory(['name' => 'Food']);
        $this->createCategory(['name' => 'Transport']);

        // Create a system category (user_id = null)
        Category::create([
            'name' => 'System Category',
            'type' => 'expense',
            'color' => '#00ff00',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/finance/categories');

        $response->assertOk();
        // Should include user categories and system categories
        $this->assertGreaterThanOrEqual(3, count($response->json('data')));
    }

    public function test_user_can_filter_by_type(): void
    {
        Sanctum::actingAs($this->user);

        $this->createCategory(['name' => 'Expense Cat', 'type' => 'expense']);
        $this->createCategory(['name' => 'Income Cat', 'type' => 'income']);

        $response = $this->getJson('/api/v1/finance/categories?type=income');

        $response->assertOk();
        foreach ($response->json('data') as $category) {
            $this->assertEquals('income', $category['type']);
        }
    }

    public function test_user_can_create_category(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/finance/categories', [
            'name' => 'New Category',
            'type' => 'expense',
            'color' => '#0000ff',
            'icon' => 'cart',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'New Category');
    }

    public function test_user_can_view_category(): void
    {
        Sanctum::actingAs($this->user);

        $category = $this->createCategory();

        $response = $this->getJson("/api/v1/finance/categories/{$category->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $category->id);
    }

    public function test_user_can_update_own_category(): void
    {
        Sanctum::actingAs($this->user);

        $category = $this->createCategory();

        $response = $this->putJson("/api/v1/finance/categories/{$category->id}", [
            'name' => 'Updated Category',
            'color' => '#00ff00',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Category')
            ->assertJsonPath('data.color', '#00ff00');
    }

    public function test_user_can_delete_own_category(): void
    {
        Sanctum::actingAs($this->user);

        $category = $this->createCategory();

        $response = $this->deleteJson("/api/v1/finance/categories/{$category->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('finance_categories', ['id' => $category->id]);
    }

    public function test_user_cannot_delete_system_category(): void
    {
        Sanctum::actingAs($this->user);

        $systemCategory = Category::create([
            'name' => 'System Category',
            'type' => 'expense',
            'color' => '#00ff00',
            'is_active' => true,
        ]);

        $response = $this->deleteJson("/api/v1/finance/categories/{$systemCategory->id}");

        $response->assertForbidden();
    }

    public function test_user_cannot_modify_other_users_category(): void
    {
        Sanctum::actingAs($this->user);

        $otherUser = User::factory()->create();
        $category = Category::create([
            'user_id' => $otherUser->id,
            'name' => 'Other Category',
            'type' => 'expense',
            'color' => '#ff0000',
            'is_active' => true,
        ]);

        $response = $this->putJson("/api/v1/finance/categories/{$category->id}", [
            'name' => 'Hacked',
        ]);

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_access_categories(): void
    {
        $response = $this->getJson('/api/v1/finance/categories');

        $response->assertUnauthorized();
    }
}
