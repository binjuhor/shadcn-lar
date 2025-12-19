<?php

namespace Modules\Permission\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Permission\Http\Requests\RoleRequest;
use Modules\Permission\Http\Resources\PermissionResource;
use Modules\Permission\Http\Resources\RoleResource;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:roles.view')->only(['index', 'show']);
        $this->middleware('can:roles.create')->only(['create', 'store']);
        $this->middleware('can:roles.edit')->only(['edit', 'update']);
        $this->middleware('can:roles.delete')->only(['destroy']);
    }

    public function index(Request $request): Response
    {
        $query = Role::with('permissions')->withCount('users');

        if ($request->filled('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        $roles = $query->orderBy('name')->paginate(10)->withQueryString();

        return Inertia::render('roles/index', [
            'roles' => RoleResource::collection($roles),
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(): Response
    {
        $permissions = Permission::orderBy('name')->get();

        return Inertia::render('roles/create', [
            'permissions' => PermissionResource::collection($permissions),
            'groupedPermissions' => $this->groupPermissions($permissions),
        ]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        $role = Role::create([
            'name' => $request->name,
            'guard_name' => 'web',
        ]);

        if ($request->filled('permissions')) {
            $role->syncPermissions($request->permissions);
        }

        return redirect()->route('dashboard.roles.index')->with('success', 'Role created successfully.');
    }

    public function show(Role $role): Response
    {
        $role->load('permissions');

        return Inertia::render('roles/show', [
            'role' => new RoleResource($role),
        ]);
    }

    public function edit(Role $role): Response
    {
        $role->load('permissions');
        $permissions = Permission::orderBy('name')->get();

        return Inertia::render('roles/edit', [
            'role' => new RoleResource($role),
            'permissions' => PermissionResource::collection($permissions),
            'groupedPermissions' => $this->groupPermissions($permissions),
            'rolePermissions' => $role->permissions->pluck('name')->toArray(),
        ]);
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        // Prevent modifying Super Admin role name
        if ($role->name === 'Super Admin' && $request->name !== 'Super Admin') {
            return back()->withErrors(['name' => 'Cannot rename Super Admin role.']);
        }

        $role->update(['name' => $request->name]);

        if ($request->has('permissions')) {
            $role->syncPermissions($request->permissions);
        }

        return redirect()->route('dashboard.roles.index')->with('success', 'Role updated successfully.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        // Prevent deleting Super Admin role
        if ($role->name === 'Super Admin') {
            return back()->withErrors(['role' => 'Cannot delete Super Admin role.']);
        }

        // Check if role has users
        if ($role->users()->count() > 0) {
            return back()->withErrors(['role' => 'Cannot delete role with assigned users.']);
        }

        $role->delete();

        return redirect()->route('dashboard.roles.index')->with('success', 'Role deleted successfully.');
    }

    private function groupPermissions($permissions): array
    {
        $grouped = [];

        foreach ($permissions as $permission) {
            $parts = explode('.', $permission->name);
            $resource = $parts[0] ?? 'other';
            $action = $parts[1] ?? $permission->name;

            if (! isset($grouped[$resource])) {
                $grouped[$resource] = [];
            }

            $grouped[$resource][] = [
                'name' => $permission->name,
                'action' => $action,
            ];
        }

        return $grouped;
    }
}
