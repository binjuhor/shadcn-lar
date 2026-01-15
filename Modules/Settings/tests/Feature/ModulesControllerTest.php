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

        $response = $this->actingAs($user)->get(route('dashboard.settings.modules'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('settings/modules/index')
            ->has('modules')
        );
    }

    public function test_non_super_admin_cannot_view_modules_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard.settings.modules'));

        $response->assertForbidden();
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function test_super_admin_can_toggle_module(): void
    {
        // This test requires separate process due to container state corruption
        // from previous test's abort() call
        Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        // Make a GET request first to initialize session with CSRF token
        $this->actingAs($user)->get(route('dashboard.settings.modules'));

        $response = $this->actingAs($user)
            ->from(route('dashboard.settings.modules'))
            ->patch(route('dashboard.settings.modules.toggle'), [
                '_token' => csrf_token(),
                'name' => 'Blog',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    public function test_cannot_disable_permission_module(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        // Make a GET request first to initialize session with CSRF token
        $this->actingAs($user)->get(route('dashboard.settings.modules'));

        $response = $this->actingAs($user)
            ->from(route('dashboard.settings.modules'))
            ->patch(route('dashboard.settings.modules.toggle'), [
                '_token' => csrf_token(),
                'name' => 'Permission',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_toggle_non_existent_module_returns_error(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        // Make a GET request first to initialize session with CSRF token
        $this->actingAs($user)->get(route('dashboard.settings.modules'));

        $response = $this->actingAs($user)
            ->from(route('dashboard.settings.modules'))
            ->patch(route('dashboard.settings.modules.toggle'), [
                '_token' => csrf_token(),
                'name' => 'NonExistentModule',
            ]);

        $response->assertNotFound();
    }

    public function test_toggle_requires_module_name(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        // Make a GET request first to initialize session with CSRF token
        $this->actingAs($user)->get(route('dashboard.settings.modules'));

        $response = $this->actingAs($user)
            ->from(route('dashboard.settings.modules'))
            ->patch(route('dashboard.settings.modules.toggle'), [
                '_token' => csrf_token(),
            ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_non_super_admin_cannot_toggle_module(): void
    {
        $user = User::factory()->create();

        // Make a GET request first to initialize session with CSRF token
        $this->actingAs($user)->get(route('dashboard.settings.modules'));

        $response = $this->actingAs($user)
            ->from(route('dashboard.settings.modules'))
            ->patch(route('dashboard.settings.modules.toggle'), [
                '_token' => csrf_token(),
                'name' => 'Blog',
            ]);

        $response->assertForbidden();
    }
}
