# Phase 1: Backend API

**Parent:** [plan.md](./plan.md)
**Dependencies:** None

## Overview

| Field | Value |
|-------|-------|
| Date | 2025-12-25 |
| Description | Create ModulesController with index/toggle endpoints |
| Priority | High |
| Status | Pending |
| Estimated | 2-3 hours |

## Key Insights

- nwidart/laravel-modules uses FileActivator by default (v11.1.0+)
- Cache system removed from package; must call `Artisan::call('module:clear')` manually
- Module status stored in `modules_statuses.json` at project root
- API: `Module::find($name)`, `Module::enable($name)`, `Module::disable($name)`, `Module::isActive($name)`

## Requirements

1. List all modules with metadata (name, alias, description, priority, status)
2. Toggle module status (enable/disable)
3. Protect Permission module from being disabled
4. Clear module cache after status change
5. Super Admin only access (`hasRole('Super Admin')`)

## Architecture

### Controller Structure

```php
ModulesController
├── index(): Response          # List all modules + metadata
└── toggle(Request): Response  # Toggle module status
```

### Route Structure

```
GET  /dashboard/settings/modules        → index (render page)
PATCH /dashboard/settings/modules/toggle → toggle (API)
```

## Related Code Files

| File | Action | Purpose |
|------|--------|---------|
| `app/Http/Controllers/ModulesController.php` | Create | Controller logic |
| `routes/dashboard.php` | Modify | Add routes |

## Implementation Steps

### Step 1: Create ModulesController

**File:** `app/Http/Controllers/ModulesController.php`

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;
use Nwidart\Modules\Facades\Module;

class ModulesController extends Controller
{
    public function index(): Response
    {
        abort_unless(auth()->user()->hasRole('Super Admin'), 403);

        $modules = collect(Module::all())->map(function ($module) {
            $config = json_decode(
                file_get_contents($module->getPath() . '/module.json'),
                true
            );

            return [
                'name' => $module->getStudlyName(),
                'alias' => $config['alias'] ?? strtolower($module->getStudlyName()),
                'description' => $config['description'] ?? '',
                'keywords' => $config['keywords'] ?? [],
                'priority' => $config['priority'] ?? 0,
                'enabled' => $module->isEnabled(),
                'isCore' => $module->getStudlyName() === 'Permission',
            ];
        })->sortBy('priority')->values();

        return Inertia::render('settings/modules/index', [
            'modules' => $modules,
        ]);
    }

    public function toggle(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->hasRole('Super Admin'), 403);

        $request->validate([
            'name' => 'required|string',
        ]);

        $moduleName = $request->input('name');
        $module = Module::find($moduleName);

        abort_unless($module, 404, 'Module not found');

        if ($moduleName === 'Permission') {
            return Redirect::back()->with('error', 'Permission module cannot be disabled.');
        }

        $wasEnabled = $module->isEnabled();

        $wasEnabled
            ? Module::disable($moduleName)
            : Module::enable($moduleName);

        Artisan::call('module:clear');

        $action = $wasEnabled ? 'disabled' : 'enabled';

        return Redirect::back()->with('success', "Module {$moduleName} {$action} successfully.");
    }
}
```

### Step 2: Add Routes

**File:** `routes/dashboard.php` (add within settings group)

```php
// Modules (Super Admin only)
Route::get('/modules', [ModulesController::class, 'index'])->name('modules');
Route::patch('/modules/toggle', [ModulesController::class, 'toggle'])->name('modules.toggle');
```

Add import at top:
```php
use App\Http\Controllers\ModulesController;
```

### Step 3: Test Backend Manually

```bash
# Via Artisan tinker
php artisan tinker
>>> \Nwidart\Modules\Facades\Module::all();
>>> \Nwidart\Modules\Facades\Module::find('Blog')->isEnabled();
```

## Todo List

- [ ] Create `app/Http/Controllers/ModulesController.php`
- [ ] Add routes to `routes/dashboard.php`
- [ ] Test index endpoint returns correct module data
- [ ] Test toggle endpoint enables/disables correctly
- [ ] Test Permission module protection
- [ ] Test non-Super Admin gets 403
- [ ] Verify cache clearing works

## Success Criteria

- [x] `GET /dashboard/settings/modules` returns module list with metadata
- [x] `PATCH /dashboard/settings/modules/toggle` toggles module status
- [x] Permission module returns error when toggle attempted
- [x] Non-Super Admin receives 403 Forbidden
- [x] Module cache cleared after toggle

## Risk Assessment

| Risk | Probability | Impact | Mitigation |
|------|-------------|--------|------------|
| Module::find returns null | Low | Medium | abort_unless check |
| File permission issues | Low | High | Use Laravel's Artisan commands |
| Race condition on status file | Very Low | Medium | Single-threaded PHP request |

## Security Considerations

- Authorization: `abort_unless(auth()->user()->hasRole('Super Admin'), 403)`
- Input validation: `$request->validate(['name' => 'required|string'])`
- Module existence check: `abort_unless($module, 404)`
- Core module protection: Hard-coded Permission check

## Next Steps

After completion, proceed to [Phase 2: Frontend UI](./phase-02-frontend-ui.md)

---

## Code Reference

### modules_statuses.json (current state)
```json
{
    "Blog": true,
    "Ecommerce": true,
    "Permission": true,
    "Notification": true,
    "Invoice": true
}
```

### module.json structure (example: Permission)
```json
{
    "name": "Permission",
    "alias": "permission",
    "description": "Role and permission management module",
    "keywords": ["roles", "permissions", "rbac", "access control"],
    "priority": 0,
    "providers": ["Modules\\Permission\\Providers\\PermissionServiceProvider"],
    "files": []
}
```
