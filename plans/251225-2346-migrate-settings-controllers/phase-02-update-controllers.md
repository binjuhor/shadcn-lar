# Phase 02: Update Controllers in Module

## Context Links

- Parent: [plan.md](./plan.md)
- Previous: [phase-01-move-requests.md](./phase-01-move-requests.md)
- Next: [phase-03-update-routes.md](./phase-03-update-routes.md)

## Overview

- **Date:** 2025-12-25
- **Priority:** High
- **Description:** Replace stub SettingsController with full implementation, add ProfileController
- **Implementation Status:** Pending
- **Review Status:** Pending

## Key Insights

- Existing `Modules/Settings/Http/Controllers/SettingsController.php` is empty stub - replace entirely
- `ModulesController.php` exists and works - keep unchanged
- ProfileController handles edit/update/destroy for user account

## Requirements

### Functional
- Replace SettingsController with full implementation (profile, account, appearance, notifications, display)
- Create ProfileController (edit, update, destroy)
- Update imports to use module request classes

### Non-functional
- Namespace: `Modules\Settings\Http\Controllers`
- Extend `App\Http\Controllers\Controller`
- Use Inertia for rendering

## Architecture

```
Modules/Settings/Http/Controllers/
├── ModulesController.php (existing - keep)
├── ProfileController.php (new)
└── SettingsController.php (replace stub)
```

## Related Code Files

**Source:**
- `app/Http/Controllers/SettingsController.php` - Full implementation
- `app/Http/Controllers/ProfileController.php` - Full implementation

**Target:**
- `Modules/Settings/Http/Controllers/SettingsController.php` - Replace
- `Modules/Settings/Http/Controllers/ProfileController.php` - Create

## Implementation Steps

1. Read current SettingsController from app/
2. Replace Modules/Settings/Http/Controllers/SettingsController.php with:
   - Namespace: `Modules\Settings\Http\Controllers`
   - Import requests from `Modules\Settings\Http\Requests`
   - Keep all methods (profile, updateProfile, account, updateAccount, appearance, updateAppearance, notifications, updateNotifications, display, updateDisplay)
3. Create Modules/Settings/Http/Controllers/ProfileController.php with:
   - Namespace: `Modules\Settings\Http\Controllers`
   - Import ProfileUpdateRequest from `Modules\Settings\Http\Requests`
   - Methods: edit, update, destroy

## Todo List

- [ ] Replace SettingsController with full implementation
- [ ] Update SettingsController request imports
- [ ] Create ProfileController
- [ ] Update ProfileController request imports
- [ ] Verify both extend base Controller

## Success Criteria

- SettingsController has all 10 methods
- ProfileController has 3 methods
- All imports use module namespaces
- No syntax errors

## Risk Assessment

| Risk | Impact | Mitigation |
|------|--------|------------|
| Import paths wrong | High | Double-check all use statements |
| Missing methods | High | Compare with original files |

## Security Considerations

- Auth middleware applied in routes (not controllers)
- Request classes handle validation

## Next Steps

Proceed to Phase 03: Update Routes
