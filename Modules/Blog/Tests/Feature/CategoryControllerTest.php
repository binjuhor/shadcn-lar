<?php

namespace Modules\Blog\Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;

class CategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createPermissions();
        $this->user = User::factory()->create();
        $this->user->givePermissionTo([
            'categories.view',
            'categories.create',
            'categories.edit',
            'categories.delete',
        ]);
    }

    protected function createPermissions(): void
    {
        $permissions = [
            'categories.view',
            'categories.create',
            'categories.edit',
            'categories.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
    }

    #[Test]
    public function it_can_list_all_categories()
    {
        Category::factory()->count(5)->create();

        $response = $this->actingAs($this->user)
            ->getJson('/dashboard/categories');

        $response->assertStatus(200)
            ->assertJsonCount(5, 'categories')
            ->assertJsonStructure([
                'categories' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'description',
                        'color',
                        'icon',
                        'is_active',
                        'posts_count',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);
    }

    #[Test]
    public function it_can_create_a_category()
    {
        $categoryData = [
            'name' => 'Technology',
            'slug' => 'technology',
            'description' => 'Tech articles and tutorials',
            'color' => '#3b82f6',
            'icon' => 'laptop',
            'is_active' => true,
            'meta_title' => 'Technology Blog',
            'meta_description' => 'Read about technology',
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/dashboard/categories', $categoryData);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Category created successfully',
                'category' => [
                    'name' => 'Technology',
                    'slug' => 'technology',
                    'description' => 'Tech articles and tutorials',
                    'color' => '#3b82f6',
                    'icon' => 'laptop',
                    'is_active' => true,
                ],
            ]);

        $this->assertDatabaseHas('categories', [
            'name' => 'Technology',
            'slug' => 'technology',
        ]);
    }

    #[Test]
    public function it_auto_generates_slug_if_not_provided()
    {
        $categoryData = [
            'name' => 'Web Development',
            'description' => 'Web dev tutorials',
            'is_active' => true,
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/dashboard/categories', $categoryData);

        $response->assertStatus(201);

        $this->assertDatabaseHas('categories', [
            'name' => 'Web Development',
            'slug' => 'web-development',
        ]);
    }

    #[Test]
    public function it_validates_required_fields_when_creating()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/dashboard/categories', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    #[Test]
    public function it_validates_unique_slug_when_creating()
    {
        Category::factory()->create(['slug' => 'technology']);

        $response = $this->actingAs($this->user)
            ->postJson('/dashboard/categories', [
                'name' => 'Tech',
                'slug' => 'technology',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    #[Test]
    public function it_can_show_a_category()
    {
        $category = Category::factory()->create([
            'name' => 'Technology',
            'slug' => 'technology',
        ]);

        Post::factory()
            ->count(3)
            ->create([
                'category_id' => $category->id,
                'status' => 'published',
            ]);

        $response = $this->actingAs($this->user)
            ->getJson("/dashboard/categories/{$category->slug}");

        $response->assertStatus(200)
            ->assertJson([
                'category' => [
                    'id' => $category->id,
                    'name' => 'Technology',
                    'slug' => 'technology',
                ],
            ])
            ->assertJsonStructure([
                'category',
                'posts' => [
                    'data' => [
                        '*' => [
                            'id',
                            'title',
                            'slug',
                            'excerpt',
                            'status',
                        ],
                    ],
                ],
            ]);
    }

    #[Test]
    public function it_can_update_a_category()
    {
        $category = Category::factory()->create([
            'name' => 'Technology',
            'slug' => 'technology',
        ]);

        $updateData = [
            'name' => 'Tech & Innovation',
            'slug' => 'tech-innovation',
            'description' => 'Updated description',
            'color' => '#10b981',
            'is_active' => true,
        ];

        $response = $this->actingAs($this->user)
            ->putJson("/dashboard/categories/{$category->slug}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Category updated successfully',
                'category' => [
                    'name' => 'Tech & Innovation',
                    'slug' => 'tech-innovation',
                    'description' => 'Updated description',
                ],
            ]);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Tech & Innovation',
            'slug' => 'tech-innovation',
        ]);
    }

    #[Test]
    public function it_validates_unique_slug_when_updating_excluding_current()
    {
        $category1 = Category::factory()->create(['slug' => 'technology']);
        $category2 = Category::factory()->create(['slug' => 'design']);

        // Should allow keeping the same slug
        $response = $this->actingAs($this->user)
            ->putJson("/dashboard/categories/{$category1->slug}", [
                'name' => 'Technology',
                'slug' => 'technology',
            ]);

        $response->assertStatus(200);

        // Should not allow using another category's slug
        $response = $this->actingAs($this->user)
            ->putJson("/dashboard/categories/{$category1->slug}", [
                'name' => 'Technology',
                'slug' => 'design',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    #[Test]
    public function it_can_create_hierarchical_categories()
    {
        $parent = Category::factory()->create([
            'name' => 'Technology',
            'slug' => 'technology',
        ]);

        $childData = [
            'name' => 'Web Development',
            'slug' => 'web-development',
            'parent_id' => $parent->id,
            'is_active' => true,
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/dashboard/categories', $childData);

        $response->assertStatus(201);

        $this->assertDatabaseHas('categories', [
            'name' => 'Web Development',
            'parent_id' => $parent->id,
        ]);
    }

    #[Test]
    public function it_prevents_circular_parent_references()
    {
        $category = Category::factory()->create();

        $response = $this->actingAs($this->user)
            ->putJson("/dashboard/categories/{$category->slug}", [
                'name' => $category->name,
                'parent_id' => $category->id,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'A category cannot be its own parent',
            ]);
    }

    #[Test]
    public function it_can_delete_a_category()
    {
        $category = Category::factory()->create();

        $response = $this->actingAs($this->user)
            ->deleteJson("/dashboard/categories/{$category->slug}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Category deleted successfully',
            ]);

        $this->assertSoftDeleted('categories', [
            'id' => $category->id,
        ]);
    }

    #[Test]
    public function it_prevents_deleting_category_with_posts()
    {
        $category = Category::factory()->create();

        Post::factory()->create([
            'category_id' => $category->id,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/dashboard/categories/{$category->slug}");

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Cannot delete category with existing posts',
            ]);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'deleted_at' => null,
        ]);
    }

    #[Test]
    public function it_prevents_deleting_category_with_children()
    {
        $parent = Category::factory()->create();

        Category::factory()->create([
            'parent_id' => $parent->id,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/dashboard/categories/{$parent->slug}");

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Cannot delete category with child categories',
            ]);

        $this->assertDatabaseHas('categories', [
            'id' => $parent->id,
            'deleted_at' => null,
        ]);
    }

    #[Test]
    public function it_requires_authentication_for_all_endpoints()
    {
        $category = Category::factory()->create();

        $this->getJson('/dashboard/categories')->assertStatus(401);
        $this->postJson('/dashboard/categories', [])->assertStatus(401);
        $this->getJson("/dashboard/categories/{$category->slug}")->assertStatus(401);
        $this->putJson("/dashboard/categories/{$category->slug}", [])->assertStatus(401);
        $this->deleteJson("/dashboard/categories/{$category->slug}")->assertStatus(401);
    }
}