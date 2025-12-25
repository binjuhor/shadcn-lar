<?php

namespace Modules\Settings\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ModulesControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
    }

    public function test_super_admin_can_view_modules_page(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        $response = $this->actingAs($user)->get('/dashboard/settings/modules');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('settings/modules/index')
            ->has('modules')
        );
    }

    public function test_non_super_admin_cannot_view_modules_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard/settings/modules');

        $response->assertStatus(403);
    }

    public function test_super_admin_can_toggle_module(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        $response = $this->actingAs($user)->patch('/dashboard/settings/modules/toggle', [
            'name' => 'Blog',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    public function test_cannot_disable_permission_module(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        $response = $this->actingAs($user)->patch('/dashboard/settings/modules/toggle', [
            'name' => 'Permission',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_toggle_non_existent_module_returns_404(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        $response = $this->actingAs($user)->patch('/dashboard/settings/modules/toggle', [
            'name' => 'NonExistentModule',
        ]);

        $response->assertStatus(404);
    }

    public function test_toggle_requires_module_name(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        $response = $this->actingAs($user)->patch('/dashboard/settings/modules/toggle', []);

        $response->assertSessionHasErrors('name');
    }

    public function test_non_super_admin_cannot_toggle_module(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patch('/dashboard/settings/modules/toggle', [
            'name' => 'Blog',
        ]);

        $response->assertStatus(403);
    }
}
