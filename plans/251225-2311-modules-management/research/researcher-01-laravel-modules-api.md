# Laravel Modules API Research Report
**Date:** 2025-12-25 | **Package:** nwidart/laravel-modules (v6+, tested in Laravel 11)

## Executive Summary
nwidart/laravel-modules provides programmatic module management via Activators (FileActivator default, DatabaseActivator custom). Core API uses `Module` class with `enable()`, `disable()`, `isActive()` methods. Cache must be cleared via `php artisan module:clear` after status changes (cache system removed v11.1.0+).

---

## 1. Core API Methods

### Module Status Management
```php
// Primary API (Module class)
Module::enable($moduleName);      // Enable module
Module::disable($moduleName);     // Disable module
Module::isActive($moduleName);    // Check status (bool)
Module::active();                 // Get active modules (array)
Module::inactive();               // Get inactive modules (array)

// Service-level access
$module = Module::find($name);
$module->enable();
$module->disable();
$module->isActive();
```

### Artisan Commands (CLI Wrapper)
```bash
php artisan module:enable BlogModule
php artisan module:disable BlogModule
php artisan module:list              # Shows all modules + status
php artisan module:clear             # Clears bootstrap/cache/modules file
```

---

## 2. FileActivator vs DatabaseActivator Trade-offs

| Feature | FileActivator | DatabaseActivator |
|---------|--------------|-------------------|
| **Storage** | `module_statuses.json` in project root | Database table |
| **Default** | Yes, recommended | Custom implementation |
| **Git Friendly** | No (tracked, conflicts in VCS) | Yes (no Git conflicts) |
| **Multi-tenancy** | Not supported natively | Supported (tenant-specific status) |
| **Performance** | Single file read (fast) | DB query (slower, cacheable) |
| **Use Case** | Small teams, single-tenant | Enterprise, SaaS, multi-tenant |

**Recommendation:** FileActivator for monolith, DatabaseActivator for multi-tenant SaaS.

---

## 3. Cache Clearing Requirements

### Post v11.1.0 (Current Standard)
- **Cache system removed** from laravel-modules core
- Manual cache clearing needed after status change:

```php
// Programmatically (after enable/disable)
Artisan::call('module:clear');  // Clears bootstrap/cache/modules
Cache::flush();                 // Optional: full app cache clear
```

### Cache Invalidation Strategy
- **Module status JSON:** `bootstrap/cache/modules` file
- **Clear when:** module status changes, migrations run, routes refreshed
- **Best practice:** Wrap enable/disable in transaction, clear after commit

```php
try {
    Module::enable('Payments');
    Artisan::call('module:clear');  // Clear immediately
    Cache::flush();                  // Optional
} catch (Exception $e) {
    Log::error('Module activation failed', ['module' => 'Payments']);
    throw $e;
}
```

---

## 4. Dependency Management

### Current State (Deprecated)
- `"requires"` field in `module.json` **no longer supported** in nwidart/laravel-modules
- Alternative package `thomasderooij/laravel-modules` offers built-in dependency tracking via `.tracker` file

### Manual Dependency Validation
```php
// Recommended approach: validate before enable
$dependencies = ['Auth', 'Users'];  // Define explicitly

foreach ($dependencies as $dep) {
    if (!Module::isActive($dep)) {
        throw new DependencyException("Module $dep must be enabled first");
    }
}

Module::enable('Orders');
```

### Cross-module Communication
- **Service Container:** Bind services in module ServiceProviders
- **Facades:** Use custom facades for cross-module access
- **Events:** Publish events, listen in dependent modules
- **Interfaces:** Define contracts, implement in dependent modules

---

## 5. Security Considerations

### Admin UI Protection
```php
// Gate/Policy check (must implement)
Gate::define('manage-modules', fn($user) => $user->isAdmin());

// In Controller
abort_unless(Auth::user()->can('manage-modules'), 403);
```

### Input Validation
```php
// Validate module name exists before enable/disable
$module = Module::find($request->module_name);
if (!$module) {
    return response()->json(['error' => 'Module not found'], 404);
}

Module::enable($module->getStudlyName());
```

### Audit Logging
```php
// Log all status changes
activity()
    ->causedBy(Auth::user())
    ->withProperties(['module' => $moduleName, 'action' => 'enable'])
    ->log('Module activated');
```

---

## 6. Admin UI Implementation Pattern

```php
// ModuleController example
public function toggle(Request $request)
{
    $this->authorize('manage-modules');

    $module = Module::find($request->module_name);
    abort_unless($module, 404);

    $isActive = $module->isActive();

    $isActive ? Module::disable($module->getStudlyName())
              : Module::enable($module->getStudlyName());

    Artisan::call('module:clear');  // Clear cache

    activity()->causedBy(Auth::user())
        ->withProperties(['module' => $request->module_name])
        ->log($isActive ? 'Module disabled' : 'Module enabled');

    return response()->json(['status' => 'success']);
}
```

---

## Key Takeaways

1. **API is simple:** Enable/disable via `Module::enable($name)`, check with `Module::isActive($name)`
2. **Choose Activator:** FileActivator for monolith, custom DatabaseActivator for multi-tenant
3. **Always clear cache:** `Artisan::call('module:clear')` after programmatic status changes
4. **No built-in deps:** Validate manually or use alternative package for dependency tracking
5. **Security first:** Gate checks, audit logging, input validation on all admin endpoints

---

## Unresolved Questions

- Does `Module::clear()` also clear route/config caches, or only module manifests?
- What's the performance impact of DatabaseActivator with 50+ modules in high-traffic SaaS?
- Are there recommended metrics/telemetry for tracking module enable/disable operations?

## Sources

- [nWidart/laravel-modules GitHub](https://github.com/nWidart/laravel-modules)
- [Laravel Modules Docs - Configuration](https://nwidart.com/laravel-modules/v6/basic-usage/configuration)
- [Module Dependency Discussion #345](https://github.com/nWidart/laravel-modules/issues/345)
- [DatabaseActivator Examples #1436](https://github.com/nWidart/laravel-modules/issues/1436)
- [Module Enable/Disable Best Practices #171](https://github.com/nWidart/laravel-modules/issues/171)
