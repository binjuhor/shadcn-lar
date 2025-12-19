<?php

namespace Modules\Permission\Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createPermissions();
        $this->user = User::factory()->create();
        $this->user->givePermissionTo([
            'roles.view',
            'roles.create',
            'roles.edit',
            'roles.delete',
        ]);
    }

    protected function createPermissions(): void
    {
        $permissions = [
            'roles.view',
            'roles.create',
            'roles.edit',
            'roles.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
    }

    #[Test]
    public function it_can_list_all_roles()
    {
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Editor', 'guard_name' => 'web']);

        $response = $this->actingAs($this->user)
            ->get('/dashboard/roles');

        $response->assertStatus(200)
            ->assertInertia(fn ($page) =>
                $page->component('roles/index')
                    ->has('roles.data', 2)
            );
    }

    #[Test]
    public function it_can_create_a_role()
    {
        $roleData = [
            'name' => 'Manager',
            'permissions' => [],
        ];

        $response = $this->actingAs($this->user)
            ->post('/dashboard/roles', $roleData);

        $response->assertRedirect();

        $this->assertDatabaseHas('roles', [
            'name' => 'Manager',
        ]);
    }

    #[Test]
    public function it_requires_authentication_for_all_endpoints()
    {
        $role = Role::create(['name' => 'Test', 'guard_name' => 'web']);

        $this->get('/dashboard/roles')->assertRedirect('/login');
        $this->post('/dashboard/roles', [])->assertRedirect('/login');
        $this->get("/dashboard/roles/{$role->id}")->assertRedirect('/login');
        $this->put("/dashboard/roles/{$role->id}", [])->assertRedirect('/login');
        $this->delete("/dashboard/roles/{$role->id}")->assertRedirect('/login');
    }
}
