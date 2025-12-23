# Phase 03: Routes & Cleanup

## Context Links
- Parent: [plan.md](./plan.md)
- Depends on: [phase-01-frontend-restructure.md](./phase-01-frontend-restructure.md)
- Depends on: [phase-02-backend-integration.md](./phase-02-backend-integration.md)

## Overview

| Field | Value |
|-------|-------|
| Date | 2024-12-19 |
| Priority | Medium |
| Implementation Status | Pending |
| Review Status | Pending |

**Description:** Fix route naming conventions, point routes to controller methods, cleanup redundant files.

## Key Insights

1. Current routes use wrong naming (e.g., `dashboard.contacts.index` for settings profile)
2. Routes use inline closures instead of controller methods
3. No PATCH/PUT routes for form submissions
4. Route naming should follow `dashboard.settings.*` pattern

## Requirements

### Functional
- All settings routes use `dashboard.settings.*` naming
- GET routes for viewing, PATCH routes for updating
- Routes point to SettingsController methods
- Clean URL structure: `/dashboard/settings/*`

### Non-Functional
- Follow Laravel route conventions
- Resource-style route naming
- Proper middleware application

## Architecture

### Current Routes (Wrong)
```php
Route::get('/', fn () => ...)->name('dashboard.contacts.index');
Route::get('/account', fn () => ...)->name('dashboard.contacts.accoubt');  // typo!
Route::get('/appearance', fn () => ...)->name('dashboard.file-manager.index');
Route::get('/display', fn () => ...)->name('dashboard.notes.index');
Route::get('/notifications', fn () => ...)->name('dashboard.scrumboard.index');
Route::get('/profile', fn () => ...)->name('dashboard.todo.index');
```

### Target Routes (Correct)
```php
Route::prefix('/settings')->name('dashboard.settings.')->group(function () {
    Route::get('/', [SettingsController::class, 'profile'])->name('profile');
    Route::patch('/profile', [SettingsController::class, 'updateProfile'])->name('profile.update');

    Route::get('/account', [SettingsController::class, 'account'])->name('account');
    Route::patch('/account', [SettingsController::class, 'updateAccount'])->name('account.update');

    Route::get('/appearance', [SettingsController::class, 'appearance'])->name('appearance');
    Route::patch('/appearance', [SettingsController::class, 'updateAppearance'])->name('appearance.update');

    Route::get('/notifications', [SettingsController::class, 'notifications'])->name('notifications');
    Route::patch('/notifications', [SettingsController::class, 'updateNotifications'])->name('notifications.update');

    Route::get('/display', [SettingsController::class, 'display'])->name('display');
    Route::patch('/display', [SettingsController::class, 'updateDisplay'])->name('display.update');
});
```

## Related Code Files

### MODIFY
| File | Changes |
|------|---------|
| `routes/dashboard.php` | Replace settings routes with controller-based routes |

### DELETE
| File | Reason |
|------|--------|
| `resources/js/pages/settings/index.tsx` | Redundant, root route now goes to profile |

## Implementation Steps

### Step 1: Update Routes File
Replace settings group in `routes/dashboard.php`:

```php
<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('dashboard/index'))->name('dashboard');

// Settings Routes - Updated with proper naming and controller methods
Route::prefix('/settings')->name('dashboard.settings.')->group(function () {
    // Profile (default settings page)
    Route::get('/', [SettingsController::class, 'profile'])->name('profile');
    Route::patch('/profile', [SettingsController::class, 'updateProfile'])->name('profile.update');

    // Account
    Route::get('/account', [SettingsController::class, 'account'])->name('account');
    Route::patch('/account', [SettingsController::class, 'updateAccount'])->name('account.update');

    // Appearance
    Route::get('/appearance', [SettingsController::class, 'appearance'])->name('appearance');
    Route::patch('/appearance', [SettingsController::class, 'updateAppearance'])->name('appearance.update');

    // Notifications
    Route::get('/notifications', [SettingsController::class, 'notifications'])->name('notifications');
    Route::patch('/notifications', [SettingsController::class, 'updateNotifications'])->name('notifications.update');

    // Display
    Route::get('/display', [SettingsController::class, 'display'])->name('display');
    Route::patch('/display', [SettingsController::class, 'updateDisplay'])->name('display.update');
});

// ... rest of routes remain unchanged
```

### Step 2: Update Nav Items URLs (if needed)
Verify `resources/js/pages/settings/data/nav-items.tsx` URLs match new routes:

```tsx
export const settingsNavItems = [
  {
    title: 'Profile',
    icon: <IconUser size={18} />,
    href: '/dashboard/settings',  // OK - maps to dashboard.settings.profile
  },
  {
    title: 'Account',
    icon: <IconTool size={18} />,
    href: '/dashboard/settings/account',  // OK
  },
  // ... etc
]
```

### Step 3: Update Form Submit URLs
Each form should submit to correct PATCH route:

| Form | URL |
|------|-----|
| profile-form | `PATCH /dashboard/settings/profile` |
| account-form | `PATCH /dashboard/settings/account` |
| appearance-form | `PATCH /dashboard/settings/appearance` |
| notifications-form | `PATCH /dashboard/settings/notifications` |
| display-form | `PATCH /dashboard/settings/display` |

### Step 4: Delete Redundant Files
Remove `resources/js/pages/settings/index.tsx` - no longer needed.

### Step 5: Verify Route List
Run artisan route:list to verify:

```bash
php artisan route:list --path=settings
```

Expected output:
```
GET|HEAD   dashboard/settings .............. dashboard.settings.profile
PATCH      dashboard/settings/profile ...... dashboard.settings.profile.update
GET|HEAD   dashboard/settings/account ...... dashboard.settings.account
PATCH      dashboard/settings/account ...... dashboard.settings.account.update
GET|HEAD   dashboard/settings/appearance ... dashboard.settings.appearance
PATCH      dashboard/settings/appearance ... dashboard.settings.appearance.update
GET|HEAD   dashboard/settings/notifications  dashboard.settings.notifications
PATCH      dashboard/settings/notifications  dashboard.settings.notifications.update
GET|HEAD   dashboard/settings/display ...... dashboard.settings.display
PATCH      dashboard/settings/display ...... dashboard.settings.display.update
```

## Todo List

- [ ] Update `routes/dashboard.php` with new settings routes
- [ ] Verify nav item URLs still work
- [ ] Update form submit URLs in frontend
- [ ] Delete `resources/js/pages/settings/index.tsx`
- [ ] Run `php artisan route:list --path=settings` to verify
- [ ] Test all settings pages load correctly
- [ ] Test all form submissions work
- [ ] Clear route cache: `php artisan route:clear`

## Success Criteria

1. All settings routes use `dashboard.settings.*` naming
2. GET routes return correct pages
3. PATCH routes update data correctly
4. No 404 errors on any settings page
5. Navigation works between all settings tabs

## Risk Assessment

| Risk | Impact | Mitigation |
|------|--------|------------|
| Broken links in app | High | Search codebase for old route names |
| Cache issues | Medium | Clear all caches after deployment |
| Middleware missing | Medium | Verify auth middleware on group |

## Security Considerations

- All routes protected by auth middleware (inherited from route file)
- PATCH routes require CSRF token (Inertia handles automatically)
- No sensitive data in URLs

## Next Steps

After Phase 3 completion:
1. Run full test suite
2. Manual QA of all settings pages
3. Update documentation if needed
4. Consider adding tests for new controller
