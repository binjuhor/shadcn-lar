# Migrate Settings Controllers to Module

## Overview

Move `SettingsController` and `ProfileController` from `app/Http/Controllers/` to `Modules/Settings/` with all related request classes.

## Current State

| Location | Files |
|----------|-------|
| `app/Http/Controllers/` | SettingsController.php, ProfileController.php |
| `app/Http/Requests/Settings/` | 5 request classes |
| `app/Http/Requests/` | ProfileUpdateRequest.php |
| `routes/dashboard.php` | Settings + Profile routes (lines 3-4, 13-28, 36-38) |
| `Modules/Settings/Http/Controllers/` | Empty stub SettingsController, ModulesController |
| `Modules/Settings/routes/web.php` | Only modules routes |

## Target State

| Location | Files |
|----------|-------|
| `Modules/Settings/Http/Controllers/` | SettingsController.php (full), ProfileController.php, ModulesController.php |
| `Modules/Settings/Http/Requests/` | 6 request classes |
| `Modules/Settings/routes/web.php` | All settings + profile routes |
| `app/Http/Controllers/` | Only Controller.php, Auth/ |
| `routes/dashboard.php` | Remove settings/profile routes |

## Status: ✅ COMPLETED

## Phases

| # | Phase | Status | File |
|---|-------|--------|------|
| 1 | Create Requests folder & move request classes | ✅ Done | [phase-01-move-requests.md](./phase-01-move-requests.md) |
| 2 | Update controllers in module | ✅ Done | [phase-02-update-controllers.md](./phase-02-update-controllers.md) |
| 3 | Update routes | ✅ Done | [phase-03-update-routes.md](./phase-03-update-routes.md) |
| 4 | Cleanup & verify | ✅ Done | [phase-04-cleanup-verify.md](./phase-04-cleanup-verify.md) |

## Key Files

**Move:**
- `app/Http/Controllers/SettingsController.php` → Replace stub in module
- `app/Http/Controllers/ProfileController.php` → New file in module
- `app/Http/Requests/Settings/*` (5 files) → Module Requests folder
- `app/Http/Requests/ProfileUpdateRequest.php` → Module Requests folder

**Update:**
- `Modules/Settings/routes/web.php` - Add settings/profile routes
- `routes/dashboard.php` - Remove migrated routes

**Delete:**
- `app/Http/Controllers/SettingsController.php`
- `app/Http/Controllers/ProfileController.php`
- `app/Http/Requests/Settings/` folder
- `app/Http/Requests/ProfileUpdateRequest.php`

## Dependencies

- Laravel Inertia (existing)
- Auth middleware (existing)
- User model (existing)

## Risks

| Risk | Impact | Mitigation |
|------|--------|------------|
| Route name conflicts | High | Keep exact same route names |
| Namespace mismatches | Medium | Update all imports carefully |
| Missing request validation | Medium | Test all forms after migration |
