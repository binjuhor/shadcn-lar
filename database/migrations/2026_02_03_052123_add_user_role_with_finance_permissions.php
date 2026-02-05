<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => 'User', 'guard_name' => 'web']);

        $permissions = [
            'accounts.view', 'accounts.create', 'accounts.edit', 'accounts.delete',
            'transactions.view', 'transactions.create', 'transactions.edit', 'transactions.delete',
            'budgets.view', 'budgets.create', 'budgets.edit', 'budgets.delete',
            'recurring-transactions.view', 'recurring-transactions.create', 'recurring-transactions.edit', 'recurring-transactions.delete',
            'settings.view', 'settings.edit',
        ];

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
        }

        $role->syncPermissions($permissions);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
