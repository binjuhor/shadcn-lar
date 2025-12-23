# Settings Module Refactor Plan

**Date:** 2024-12-19
**Status:** Draft
**Priority:** Medium

## Objective

Refactor the settings page frontend module to follow the consistent module pattern used by `users` and `tasks` modules, including context provider, data schema, proper route naming, and backend controller integration.

## Current State Summary

| Aspect | Current | Target |
|--------|---------|--------|
| State Management | None | Context Provider |
| Data Validation | Inline Zod in forms | Centralized schema.ts |
| Backend | None (toast only) | SettingsController.php |
| Routes | Inline closures, wrong names | Controller methods, `dashboard.settings.*` |
| Layout | SettingLayout with embedded nav | Clean layout, nav config extracted |

## Implementation Phases

| # | Phase | Status | Progress | File |
|---|-------|--------|----------|------|
| 1 | Frontend Restructure | Pending | 0% | [phase-01-frontend-restructure.md](./phase-01-frontend-restructure.md) |
| 2 | Backend Integration | Pending | 0% | [phase-02-backend-integration.md](./phase-02-backend-integration.md) |
| 3 | Routes & Cleanup | Pending | 0% | [phase-03-routes-cleanup.md](./phase-03-routes-cleanup.md) |

## File Changes Summary

### CREATE
- `resources/js/pages/settings/context/settings-context.tsx`
- `resources/js/pages/settings/data/schema.ts`
- `resources/js/pages/settings/data/nav-items.tsx`
- `app/Http/Controllers/SettingsController.php`

### MODIFY
- `resources/js/layouts/settings-layout/index.tsx`
- `resources/js/pages/settings/profile/index.tsx`
- `resources/js/pages/settings/account/index.tsx`
- `resources/js/pages/settings/appearance/index.tsx`
- `resources/js/pages/settings/notifications/index.tsx`
- `resources/js/pages/settings/display/index.tsx`
- `routes/dashboard.php`

### DELETE
- `resources/js/pages/settings/index.tsx` (redundant)

## Dependencies

- Existing `use-dialog-state` hook
- Existing `SettingLayout` component
- Laravel Inertia.js integration

## Success Criteria

1. Settings module follows same pattern as users/tasks modules
2. Route naming is consistent: `dashboard.settings.*`
3. Forms submit to backend API via Inertia
4. TypeScript types properly defined
5. No breaking changes to existing functionality

## Design Decisions

1. **Settings storage:** All settings (including appearance) persist to database per-user
2. **API validation:** Use Laravel FormRequest classes for server-side validation
