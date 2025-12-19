<?php

namespace Modules\Permission\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Permission\Http\Requests\UserRequest;
use Modules\Permission\Http\Resources\RoleResource;
use Modules\Permission\Http\Resources\UserResource;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:users.view')->only(['index', 'show']);
        $this->middleware('can:users.create')->only(['create', 'store']);
        $this->middleware('can:users.edit')->only(['edit', 'update']);
        $this->middleware('can:users.delete')->only(['destroy']);
    }

    public function index(Request $request): Response
    {
        $query = User::with('roles');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                    ->orWhere('email', 'like', "%{$request->search}%");
            });
        }

        if ($request->filled('role')) {
            $query->whereHas('roles', function ($q) use ($request) {
                $q->where('name', $request->role);
            });
        }

        $users = $query->orderBy('name')->paginate(10)->withQueryString();
        $roles = Role::orderBy('name')->get();

        return Inertia::render('users/index', [
            'users' => UserResource::collection($users),
            'roles' => RoleResource::collection($roles),
            'filters' => $request->only(['search', 'role']),
        ]);
    }

    public function create(): Response
    {
        $roles = Role::orderBy('name')->get();

        return Inertia::render('users/create', [
            'roles' => RoleResource::collection($roles),
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        if ($request->filled('roles')) {
            $user->syncRoles($request->roles);
        }

        return redirect()->route('dashboard.users.index')->with('success', 'User created successfully.');
    }

    public function show(User $user): Response
    {
        $user->load('roles.permissions');

        return Inertia::render('users/show', [
            'user' => new UserResource($user),
        ]);
    }

    public function edit(User $user): Response
    {
        $user->load('roles');
        $roles = Role::orderBy('name')->get();

        return Inertia::render('users/edit', [
            'user' => new UserResource($user),
            'roles' => RoleResource::collection($roles),
            'userRoles' => $user->roles->pluck('name')->toArray(),
        ]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $data = [
            'name' => $request->name,
            'email' => $request->email,
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        if ($request->has('roles')) {
            $user->syncRoles($request->roles);
        }

        return redirect()->route('dashboard.users.index')->with('success', 'User updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        // Prevent self-deletion
        if ($user->id === auth()->id()) {
            return back()->withErrors(['user' => 'Cannot delete your own account.']);
        }

        // Prevent deleting last Super Admin
        if ($user->hasRole('Super Admin')) {
            $superAdminCount = User::role('Super Admin')->count();
            if ($superAdminCount <= 1) {
                return back()->withErrors(['user' => 'Cannot delete the last Super Admin.']);
            }
        }

        $user->delete();

        return redirect()->route('dashboard.users.index')->with('success', 'User deleted successfully.');
    }

    public function bulkAssignRoles(Request $request): RedirectResponse
    {
        $request->validate([
            'user_ids' => ['required', 'array'],
            'user_ids.*' => ['exists:users,id'],
            'roles' => ['required', 'array'],
            'roles.*' => ['exists:roles,name'],
        ]);

        $users = User::whereIn('id', $request->user_ids)->get();

        foreach ($users as $user) {
            $user->syncRoles($request->roles);
        }

        return back()->with('success', 'Roles assigned to ' . count($request->user_ids) . ' users.');
    }
}
