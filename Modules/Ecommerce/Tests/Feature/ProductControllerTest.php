<?php

namespace Modules\Ecommerce\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Ecommerce\Models\Product;
use Modules\Ecommerce\Models\ProductCategory;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createPermissions();
        $this->user = User::factory()->create();
        $this->user->givePermissionTo([
            'products.view',
            'products.create',
            'products.edit',
            'products.delete',
        ]);
    }

    protected function createPermissions(): void
    {
        $permissions = [
            'products.view',
            'products.create',
            'products.edit',
            'products.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
    }

    #[Test]
    public function it_can_list_all_products()
    {
        Product::factory()->count(5)->create();

        $response = $this->actingAs($this->user)
            ->get('/dashboard/ecommerce/products');

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('ecommerce/products')
                ->has('products.data', 5)
            );
    }

    #[Test]
    public function it_can_create_a_product()
    {
        $category = ProductCategory::factory()->create();

        $productData = [
            'name' => 'Test Product',
            'description' => 'Test description',
            'price' => 99.99,
            'stock_quantity' => 10,
            'status' => 'active',
            'category_id' => $category->id,
        ];

        $response = $this->actingAs($this->user)
            ->post('/dashboard/ecommerce/products', $productData);

        $response->assertRedirect();

        $this->assertDatabaseHas('products', [
            'name' => 'Test Product',
            'price' => 99.99,
        ]);
    }

    #[Test]
    public function it_can_show_a_product()
    {
        $product = Product::factory()->create();

        $response = $this->actingAs($this->user)
            ->get("/dashboard/ecommerce/products/{$product->slug}");

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('ecommerce/product')
                ->has('product')
            );
    }

    #[Test]
    public function it_can_update_a_product()
    {
        $product = Product::factory()->create();

        $updateData = [
            'name' => 'Updated Product',
            'description' => 'Updated description',
            'price' => 149.99,
            'stock_quantity' => 20,
            'status' => 'active',
        ];

        $response = $this->actingAs($this->user)
            ->put("/dashboard/ecommerce/products/{$product->slug}", $updateData);

        $response->assertRedirect();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Updated Product',
            'price' => 149.99,
        ]);
    }

    #[Test]
    public function it_can_delete_a_product()
    {
        $product = Product::factory()->create();

        $response = $this->actingAs($this->user)
            ->delete("/dashboard/ecommerce/products/{$product->slug}");

        $response->assertRedirect();

        $this->assertSoftDeleted('products', [
            'id' => $product->id,
        ]);
    }

    #[Test]
    public function it_requires_authentication_for_all_endpoints()
    {
        $product = Product::factory()->create();

        $this->get('/dashboard/ecommerce/products')->assertRedirect('/login');
        $this->post('/dashboard/ecommerce/products', [])->assertRedirect('/login');
        $this->get("/dashboard/ecommerce/products/{$product->slug}")->assertRedirect('/login');
        $this->put("/dashboard/ecommerce/products/{$product->slug}", [])->assertRedirect('/login');
        $this->delete("/dashboard/ecommerce/products/{$product->slug}")->assertRedirect('/login');
    }
}
