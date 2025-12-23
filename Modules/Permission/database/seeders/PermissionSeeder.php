<?php

namespace Modules\Permission\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Clear cached permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $config = config('permission');
        $permissionsConfig = $config['permissions'] ?? [];
        $rolePermissionsConfig = $config['role_permissions'] ?? [];
        $defaultRoles = $config['default_roles'] ?? [];

        // Create all permissions
        $allPermissions = [];
        foreach ($permissionsConfig as $resource => $actions) {
            foreach ($actions as $action) {
                $permissionName = "{$resource}.{$action}";
                Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
                $allPermissions[] = $permissionName;
            }
        }

        $this->command->info('Created ' . count($allPermissions) . ' permissions');

        // Create roles and assign permissions
        foreach ($defaultRoles as $roleName) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

            $rolePermissions = $rolePermissionsConfig[$roleName] ?? [];

            if ($rolePermissions === '*') {
                // Super Admin gets all permissions via Gate::before, no need to assign
                $this->command->info("Created role: {$roleName} (bypass via Gate::before)");
                continue;
            }

            // Expand wildcard permissions (e.g., 'posts.*' -> 'posts.view', 'posts.create', etc.)
            $expandedPermissions = $this->expandPermissions($rolePermissions, $permissionsConfig);

            $role->syncPermissions($expandedPermissions);
            $this->command->info("Created role: {$roleName} with " . count($expandedPermissions) . ' permissions');
        }

        // Create test super admin user if not exists
        $this->createTestSuperAdmin();

        // Clear cache again after seeding
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    private function expandPermissions(array $rolePermissions, array $permissionsConfig): array
    {
        $expanded = [];

        foreach ($rolePermissions as $permission) {
            if (str_ends_with($permission, '.*')) {
                // Wildcard: expand to all actions for this resource
                $resource = str_replace('.*', '', $permission);
                if (isset($permissionsConfig[$resource])) {
                    foreach ($permissionsConfig[$resource] as $action) {
                        $expanded[] = "{$resource}.{$action}";
                    }
                }
            } else {
                $expanded[] = $permission;
            }
        }

        return array_unique($expanded);
    }

    private function createTestSuperAdmin(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'shadcn@gmail.com'],
            [
                'name' => 'Super Admin',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]
        );

        if (! $user->hasRole('Super Admin')) {
            $user->assignRole('Super Admin');
            $this->command->info('Assigned Super Admin role to shadcn@gmail.com');
        }
    }
}
