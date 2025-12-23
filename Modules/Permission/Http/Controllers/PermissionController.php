<?php

namespace Modules\Permission\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Permission\Http\Resources\PermissionResource;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:permissions.view')->only(['index', 'show']);
        $this->middleware('can:permissions.create')->only(['create', 'store']);
        $this->middleware('can:permissions.edit')->only(['edit', 'update']);
        $this->middleware('can:permissions.delete')->only(['destroy']);
    }

    public function index(Request $request): Response
    {
        $query = Permission::withCount('roles');

        if ($request->filled('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        if ($request->filled('group')) {
            $query->where('name', 'like', "{$request->group}.%");
        }

        $permissions = $query->orderBy('name')->paginate(20)->withQueryString();

        $groups = Permission::selectRaw("SUBSTRING_INDEX(name, '.', 1) as group_name")
            ->distinct()
            ->orderBy('group_name')
            ->pluck('group_name');

        return Inertia::render('permissions/index', [
            'permissions' => [
                'data' => PermissionResource::collection($permissions->items())->resolve(),
                'current_page' => $permissions->currentPage(),
                'last_page' => $permissions->lastPage(),
                'per_page' => $permissions->perPage(),
                'total' => $permissions->total(),
            ],
            'groups' => $groups,
            'filters' => $request->only(['search', 'group']),
        ]);
    }

    public function create(): Response
    {
        $groups = Permission::selectRaw("SUBSTRING_INDEX(name, '.', 1) as group_name")
            ->distinct()
            ->orderBy('group_name')
            ->pluck('group_name');

        return Inertia::render('permissions/create', [
            'groups' => $groups,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:permissions,name'],
        ]);

        Permission::create(['name' => $validated['name'], 'guard_name' => 'web']);

        return redirect()->route('dashboard.permissions.index')
            ->with('success', 'Permission created successfully.');
    }

    public function edit(Permission $permission): Response
    {
        return Inertia::render('permissions/edit', [
            'permission' => (new PermissionResource($permission))->resolve(),
        ]);
    }

    public function update(Request $request, Permission $permission): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('permissions', 'name')->ignore($permission->id)],
        ]);

        $permission->update(['name' => $validated['name']]);

        return redirect()->route('dashboard.permissions.index')
            ->with('success', 'Permission updated successfully.');
    }

    public function destroy(Permission $permission): RedirectResponse
    {
        if ($permission->roles()->count() > 0) {
            return back()->withErrors(['permission' => 'Cannot delete permission assigned to roles.']);
        }

        $permission->delete();

        return redirect()->route('dashboard.permissions.index')
            ->with('success', 'Permission deleted successfully.');
    }
}
