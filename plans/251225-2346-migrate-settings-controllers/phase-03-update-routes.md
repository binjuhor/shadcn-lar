# Phase 03: Update Routes

## Context Links

- Parent: [plan.md](./plan.md)
- Previous: [phase-02-update-controllers.md](./phase-02-update-controllers.md)
- Next: [phase-04-cleanup-verify.md](./phase-04-cleanup-verify.md)

## Overview

- **Date:** 2025-12-25
- **Priority:** High
- **Description:** Move settings/profile routes from dashboard.php to Settings module routes
- **Implementation Status:** Pending
- **Review Status:** Pending

## Key Insights

- Current routes in `routes/dashboard.php` lines 13-28 (settings) and 36-38 (profile)
- Module already has `Modules/Settings/routes/web.php` with modules routes
- Must preserve exact route names for frontend compatibility

## Requirements

### Functional
- Add settings routes to module web.php
- Add profile routes to module web.php
- Remove from dashboard.php
- Keep route names identical

### Non-functional
- Middleware: `auth`, `verified`
- Prefix: `dashboard/settings` and `dashboard`

## Architecture

Routes to move:

```php
// Settings (prefix: dashboard/settings, name: dashboard.settings.)
GET  /                -> profile
PATCH /profile        -> profile.update
GET  /account         -> account
PATCH /account        -> account.update
GET  /appearance      -> appearance
PATCH /appearance     -> appearance.update
GET  /notifications   -> notifications
PATCH /notifications  -> notifications.update
GET  /display         -> display
PATCH /display        -> display.update

// Profile (prefix: dashboard, name: root)
GET    /profile       -> profile.edit
PATCH  /profile       -> profile.update
DELETE /profile       -> profile.destroy
```

## Related Code Files

**Modify:**
- `Modules/Settings/routes/web.php` - Add all settings + profile routes
- `routes/dashboard.php` - Remove lines 3-4 (imports), 13-28 (settings group), 36-38 (profile)

## Implementation Steps

1. Update `Modules/Settings/routes/web.php`:
   - Add imports for SettingsController, ProfileController
   - Add settings routes group (same structure as dashboard.php)
   - Add profile routes (edit, update, destroy)
2. Update `routes/dashboard.php`:
   - Remove `use App\Http\Controllers\ProfileController;`
   - Remove `use App\Http\Controllers\SettingsController;`
   - Remove settings prefix group (lines 13-28)
   - Remove profile routes (lines 36-38)

## Todo List

- [ ] Add SettingsController import to module routes
- [ ] Add ProfileController import to module routes
- [ ] Add settings routes group
- [ ] Add profile routes
- [ ] Remove imports from dashboard.php
- [ ] Remove settings group from dashboard.php
- [ ] Remove profile routes from dashboard.php

## Success Criteria

- All routes accessible at same URLs
- Route names unchanged
- No duplicate route definitions
- `php artisan route:list` shows correct controllers

## Risk Assessment

| Risk | Impact | Mitigation |
|------|--------|------------|
| Route name conflict | High | Remove from dashboard.php before adding to module |
| Missing middleware | High | Copy exact middleware array |
| Wrong prefix | High | Verify prefix matches original |

## Security Considerations

- Auth + verified middleware must be applied
- Same authorization as before

## Next Steps

Proceed to Phase 04: Cleanup & Verify
