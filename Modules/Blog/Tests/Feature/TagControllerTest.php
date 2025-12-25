<?php

namespace Modules\Blog\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Blog\Models\Post;
use Modules\Blog\Models\Tag;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TagControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createPermissions();
        $this->user = User::factory()->create();
        $this->user->givePermissionTo([
            'tags.view',
            'tags.create',
            'tags.edit',
            'tags.delete',
        ]);
    }

    protected function createPermissions(): void
    {
        $permissions = [
            'tags.view',
            'tags.create',
            'tags.edit',
            'tags.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
    }

    #[Test]
    public function it_can_list_all_tags()
    {
        Tag::factory()->count(5)->create();

        $response = $this->actingAs($this->user)
            ->getJson('/dashboard/tags');

        $response->assertStatus(200)
            ->assertJsonCount(5, 'tags')
            ->assertJsonStructure([
                'tags' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'description',
                        'color',
                        'is_active',
                        'usage_count',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);
    }

    #[Test]
    public function it_orders_tags_by_usage_count()
    {
        $tag1 = Tag::factory()->create(['usage_count' => 5]);
        $tag2 = Tag::factory()->create(['usage_count' => 10]);
        $tag3 = Tag::factory()->create(['usage_count' => 3]);

        $response = $this->actingAs($this->user)
            ->getJson('/dashboard/tags');

        $tags = $response->json('tags');

        $this->assertEquals($tag2->id, $tags[0]['id']);
        $this->assertEquals($tag1->id, $tags[1]['id']);
        $this->assertEquals($tag3->id, $tags[2]['id']);
    }

    #[Test]
    public function it_can_create_a_tag()
    {
        $tagData = [
            'name' => 'Laravel',
            'slug' => 'laravel',
            'description' => 'Laravel framework articles',
            'color' => '#ff2d20',
            'is_active' => true,
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/dashboard/tags', $tagData);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Tag created successfully',
                'tag' => [
                    'name' => 'Laravel',
                    'slug' => 'laravel',
                    'description' => 'Laravel framework articles',
                    'color' => '#ff2d20',
                    'is_active' => true,
                ],
            ]);

        $this->assertDatabaseHas('tags', [
            'name' => 'Laravel',
            'slug' => 'laravel',
        ]);
    }

    #[Test]
    public function it_auto_generates_slug_if_not_provided()
    {
        $tagData = [
            'name' => 'Vue.js',
            'description' => 'Vue framework',
            'is_active' => true,
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/dashboard/tags', $tagData);

        $response->assertStatus(201);

        $this->assertDatabaseHas('tags', [
            'name' => 'Vue.js',
            'slug' => 'vuejs',
        ]);
    }

    #[Test]
    public function it_validates_required_fields_when_creating()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/dashboard/tags', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    #[Test]
    public function it_validates_unique_slug_when_creating()
    {
        Tag::factory()->create(['slug' => 'laravel']);

        $response = $this->actingAs($this->user)
            ->postJson('/dashboard/tags', [
                'name' => 'Laravel PHP',
                'slug' => 'laravel',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    #[Test]
    public function it_can_show_a_tag_with_posts()
    {
        $tag = Tag::factory()->create([
            'name' => 'Laravel',
            'slug' => 'laravel',
        ]);

        $posts = Post::factory()
            ->count(3)
            ->create(['status' => 'published']);

        $tag->posts()->attach($posts->pluck('id'));

        $response = $this->actingAs($this->user)
            ->getJson("/dashboard/tags/{$tag->slug}");

        $response->assertStatus(200)
            ->assertJson([
                'tag' => [
                    'id' => $tag->id,
                    'name' => 'Laravel',
                    'slug' => 'laravel',
                ],
            ])
            ->assertJsonStructure([
                'tag',
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
    public function it_can_update_a_tag()
    {
        $tag = Tag::factory()->create([
            'name' => 'Laravel',
            'slug' => 'laravel',
        ]);

        $updateData = [
            'name' => 'Laravel Framework',
            'slug' => 'laravel-framework',
            'description' => 'Updated description',
            'color' => '#10b981',
            'is_active' => true,
        ];

        $response = $this->actingAs($this->user)
            ->putJson("/dashboard/tags/{$tag->slug}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Tag updated successfully',
                'tag' => [
                    'name' => 'Laravel Framework',
                    'slug' => 'laravel-framework',
                    'description' => 'Updated description',
                ],
            ]);

        $this->assertDatabaseHas('tags', [
            'id' => $tag->id,
            'name' => 'Laravel Framework',
            'slug' => 'laravel-framework',
        ]);
    }

    #[Test]
    public function it_validates_unique_slug_when_updating_excluding_current()
    {
        $tag1 = Tag::factory()->create(['slug' => 'laravel']);
        $tag2 = Tag::factory()->create(['slug' => 'vue']);

        // Should allow keeping the same slug
        $response = $this->actingAs($this->user)
            ->putJson("/dashboard/tags/{$tag1->slug}", [
                'name' => 'Laravel',
                'slug' => 'laravel',
            ]);

        $response->assertStatus(200);

        // Should not allow using another tag's slug
        $response = $this->actingAs($this->user)
            ->putJson("/dashboard/tags/{$tag1->slug}", [
                'name' => 'Laravel',
                'slug' => 'vue',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    #[Test]
    public function it_can_delete_a_tag()
    {
        $tag = Tag::factory()->create();

        $response = $this->actingAs($this->user)
            ->deleteJson("/dashboard/tags/{$tag->slug}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Tag deleted successfully',
            ]);

        $this->assertSoftDeleted('tags', [
            'id' => $tag->id,
        ]);
    }

    #[Test]
    public function it_prevents_deleting_tag_with_posts()
    {
        $tag = Tag::factory()->create();

        $post = Post::factory()->create();
        $tag->posts()->attach($post->id);

        $response = $this->actingAs($this->user)
            ->deleteJson("/dashboard/tags/{$tag->slug}");

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Cannot delete tag with existing posts',
            ]);

        $this->assertDatabaseHas('tags', [
            'id' => $tag->id,
            'deleted_at' => null,
        ]);
    }

    #[Test]
    public function it_can_get_popular_tags()
    {
        $tag1 = Tag::factory()->create(['usage_count' => 50, 'is_active' => true]);
        $tag2 = Tag::factory()->create(['usage_count' => 100, 'is_active' => true]);
        $tag3 = Tag::factory()->create(['usage_count' => 25, 'is_active' => true]);
        $tag4 = Tag::factory()->create(['usage_count' => 75, 'is_active' => false]);

        $response = $this->actingAs($this->user)
            ->getJson('/dashboard/tags/popular');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'tags'); // Only active tags

        $tags = $response->json('tags');

        // Should be ordered by usage count descending
        $this->assertEquals($tag2->id, $tags[0]['id']);
        $this->assertEquals($tag1->id, $tags[1]['id']);
        $this->assertEquals($tag3->id, $tags[2]['id']);
    }

    #[Test]
    public function it_limits_popular_tags_to_20()
    {
        Tag::factory()->count(25)->create(['is_active' => true]);

        $response = $this->actingAs($this->user)
            ->getJson('/dashboard/tags/popular');

        $response->assertStatus(200)
            ->assertJsonCount(20, 'tags');
    }

    #[Test]
    public function it_requires_authentication_for_all_endpoints()
    {
        $tag = Tag::factory()->create();

        $this->getJson('/dashboard/tags')->assertStatus(401);
        $this->postJson('/dashboard/tags', [])->assertStatus(401);
        $this->getJson("/dashboard/tags/{$tag->slug}")->assertStatus(401);
        $this->putJson("/dashboard/tags/{$tag->slug}", [])->assertStatus(401);
        $this->deleteJson("/dashboard/tags/{$tag->slug}")->assertStatus(401);
        $this->getJson('/dashboard/tags/popular')->assertStatus(401);
    }

    #[Test]
    public function it_updates_usage_count_when_tag_is_used()
    {
        $tag = Tag::factory()->create(['usage_count' => 0]);
        $post = Post::factory()->create();

        $tag->posts()->attach($post->id);
        $tag->updateUsageCount();

        $this->assertDatabaseHas('tags', [
            'id' => $tag->id,
            'usage_count' => 1,
        ]);
    }
}
