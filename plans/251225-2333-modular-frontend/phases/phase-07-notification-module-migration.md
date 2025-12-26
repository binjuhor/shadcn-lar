# Phase 07: Notification Module Migration

**Date:** 2025-12-25
**Priority:** P1
**Status:** pending
**Estimated Effort:** 1-2 hours
**Depends On:** Phase 03

---

## Overview

Migrate Notification module frontend pages from `resources/js/pages/notifications/` to `Modules/Notification/resources/js/pages/`. Preserves nested structure for send/ and templates/.

## Files to Migrate

| Source | Target | Controller Change |
|--------|--------|-------------------|
| `notifications/index.tsx` | `Notification/pages/index.tsx` | `Notification::index` |
| `notifications/send/index.tsx` | `Notification/pages/send/index.tsx` | `Notification::send/index` |
| `notifications/templates/index.tsx` | `Notification/pages/templates/index.tsx` | `Notification::templates/index` |
| `notifications/templates/create.tsx` | `Notification/pages/templates/create.tsx` | `Notification::templates/create` |
| `notifications/templates/edit.tsx` | `Notification/pages/templates/edit.tsx` | `Notification::templates/edit` |

**Total: 5 files**

## Related Code Files

```
# Source pages
/Users/binjuhor/Development/shadcn-admin/resources/js/pages/notifications/index.tsx
/Users/binjuhor/Development/shadcn-admin/resources/js/pages/notifications/send/index.tsx
/Users/binjuhor/Development/shadcn-admin/resources/js/pages/notifications/templates/index.tsx
/Users/binjuhor/Development/shadcn-admin/resources/js/pages/notifications/templates/create.tsx
/Users/binjuhor/Development/shadcn-admin/resources/js/pages/notifications/templates/edit.tsx

# Controllers to update
/Users/binjuhor/Development/shadcn-admin/Modules/Notification/Http/Controllers/NotificationsController.php
/Users/binjuhor/Development/shadcn-admin/Modules/Notification/Http/Controllers/TemplatesController.php
```

## Implementation Steps

### 1. Create Directory Structure

```bash
mkdir -p Modules/Notification/resources/js/pages/send
mkdir -p Modules/Notification/resources/js/pages/templates
```

### 2. Move Page Files

```bash
# Main page
mv resources/js/pages/notifications/index.tsx Modules/Notification/resources/js/pages/

# Send subdirectory
mv resources/js/pages/notifications/send/index.tsx Modules/Notification/resources/js/pages/send/

# Templates subdirectory
mv resources/js/pages/notifications/templates/index.tsx Modules/Notification/resources/js/pages/templates/
mv resources/js/pages/notifications/templates/create.tsx Modules/Notification/resources/js/pages/templates/
mv resources/js/pages/notifications/templates/edit.tsx Modules/Notification/resources/js/pages/templates/

# Remove empty directories
rmdir resources/js/pages/notifications/send
rmdir resources/js/pages/notifications/templates
rmdir resources/js/pages/notifications
```

### 3. Update Controllers

**NotificationsController.php:**
```php
// Before
return Inertia::render('notifications/index', [...]);
return Inertia::render('notifications/send/index', [...]);

// After
return Inertia::render('Notification::index', [...]);
return Inertia::render('Notification::send/index', [...]);
```

**TemplatesController.php:**
```php
// Before
return Inertia::render('notifications/templates/index', [...]);
return Inertia::render('notifications/templates/create', [...]);
return Inertia::render('notifications/templates/edit', [...]);

// After
return Inertia::render('Notification::templates/index', [...]);
return Inertia::render('Notification::templates/create', [...]);
return Inertia::render('Notification::templates/edit', [...]);
```

### 4. Test Each Route

```bash
# Test routes in browser
# /notifications
# /notifications/send
# /notifications/templates
# /notifications/templates/create
# /notifications/templates/{id}/edit
```

## Todo List

- [ ] Create Notification module pages directory structure
- [ ] Move index.tsx
- [ ] Move send/index.tsx
- [ ] Move templates/index.tsx
- [ ] Move templates/create.tsx
- [ ] Move templates/edit.tsx
- [ ] Update NotificationsController.php
- [ ] Update TemplatesController.php (if separate)
- [ ] Test notifications list route
- [ ] Test send notification route
- [ ] Test templates list route
- [ ] Test template create route
- [ ] Test template edit route
- [ ] Remove empty source directories

## Success Criteria

1. All 5 notification pages load correctly
2. Template CRUD operations work
3. Send notification functionality works
4. No TypeScript errors in moved files
5. HMR works for module pages

## Risk Assessment

| Risk | Impact | Mitigation |
|------|--------|------------|
| Nested routes break | Medium | Preserve directory structure |
| Template form errors | Medium | Test form submissions |
| Send page not working | Medium | Test full send workflow |

## Rollback Plan

If issues arise:
```bash
git checkout -- resources/js/pages/notifications/
git checkout -- Modules/Notification/Http/Controllers/
```

## Next Steps

After completion, proceed to Phase 08: Permission Module Migration
