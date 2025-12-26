# Phase 08: Permission Module Migration

**Date:** 2025-12-25
**Priority:** P1
**Status:** pending
**Estimated Effort:** 3-4 hours
**Depends On:** Phase 03

---

## Overview

Migrate Permission module frontend pages from multiple source directories (`permissions/`, `roles/`, `users/`) to `Modules/Permission/resources/js/pages/`. This consolidates all permission-related pages under one module.

## Files to Migrate

### Permissions (3 files)
| Source | Target | Controller Change |
|--------|--------|-------------------|
| `permissions/index.tsx` | `Permission/pages/permissions/index.tsx` | `Permission::permissions/index` |
| `permissions/create.tsx` | `Permission/pages/permissions/create.tsx` | `Permission::permissions/create` |
| `permissions/edit.tsx` | `Permission/pages/permissions/edit.tsx` | `Permission::permissions/edit` |

### Roles (3 files)
| Source | Target | Controller Change |
|--------|--------|-------------------|
| `roles/index.tsx` | `Permission/pages/roles/index.tsx` | `Permission::roles/index` |
| `roles/create.tsx` | `Permission/pages/roles/create.tsx` | `Permission::roles/create` |
| `roles/edit.tsx` | `Permission/pages/roles/edit.tsx` | `Permission::roles/edit` |

### Users - Main (3 files)
| Source | Target | Controller Change |
|--------|--------|-------------------|
| `users/index.tsx` | `Permission/pages/users/index.tsx` | `Permission::users/index` |
| `users/create.tsx` | `Permission/pages/users/create.tsx` | `Permission::users/create` |
| `users/edit.tsx` | `Permission/pages/users/edit.tsx` | `Permission::users/edit` |

### Users - Context (1 file)
| Source | Target |
|--------|--------|
| `users/context/users-context.tsx` | `Permission/pages/users/context/users-context.tsx` |

### Users - Components (10 files)
| Source | Target |
|--------|--------|
| `users/components/users-table.tsx` | `Permission/pages/users/components/users-table.tsx` |
| `users/components/users-columns.tsx` | `Permission/pages/users/components/users-columns.tsx` |
| `users/components/users-dialogs.tsx` | `Permission/pages/users/components/users-dialogs.tsx` |
| `users/components/users-delete-dialog.tsx` | `Permission/pages/users/components/users-delete-dialog.tsx` |
| `users/components/users-primary-buttons.tsx` | `Permission/pages/users/components/users-primary-buttons.tsx` |
| `users/components/data-table-column-header.tsx` | `Permission/pages/users/components/data-table-column-header.tsx` |
| `users/components/data-table-pagination.tsx` | `Permission/pages/users/components/data-table-pagination.tsx` |
| `users/components/data-table-row-actions.tsx` | `Permission/pages/users/components/data-table-row-actions.tsx` |
| `users/components/data-table-toolbar.tsx` | `Permission/pages/users/components/data-table-toolbar.tsx` |
| `users/components/data-table-view-options.tsx` | `Permission/pages/users/components/data-table-view-options.tsx` |

**Total: 20 files**

## Related Code Files

```
# Source pages - Permissions
/Users/binjuhor/Development/shadcn-admin/resources/js/pages/permissions/index.tsx
/Users/binjuhor/Development/shadcn-admin/resources/js/pages/permissions/create.tsx
/Users/binjuhor/Development/shadcn-admin/resources/js/pages/permissions/edit.tsx

# Source pages - Roles
/Users/binjuhor/Development/shadcn-admin/resources/js/pages/roles/index.tsx
/Users/binjuhor/Development/shadcn-admin/resources/js/pages/roles/create.tsx
/Users/binjuhor/Development/shadcn-admin/resources/js/pages/roles/edit.tsx

# Source pages - Users
/Users/binjuhor/Development/shadcn-admin/resources/js/pages/users/index.tsx
/Users/binjuhor/Development/shadcn-admin/resources/js/pages/users/create.tsx
/Users/binjuhor/Development/shadcn-admin/resources/js/pages/users/edit.tsx
/Users/binjuhor/Development/shadcn-admin/resources/js/pages/users/context/users-context.tsx
/Users/binjuhor/Development/shadcn-admin/resources/js/pages/users/components/*.tsx (10 files)

# Controllers to update
/Users/binjuhor/Development/shadcn-admin/Modules/Permission/Http/Controllers/PermissionsController.php
/Users/binjuhor/Development/shadcn-admin/Modules/Permission/Http/Controllers/RolesController.php
/Users/binjuhor/Development/shadcn-admin/Modules/Permission/Http/Controllers/UsersController.php
```

## Implementation Steps

### 1. Create Directory Structure

```bash
mkdir -p Modules/Permission/resources/js/pages/permissions
mkdir -p Modules/Permission/resources/js/pages/roles
mkdir -p Modules/Permission/resources/js/pages/users/components
mkdir -p Modules/Permission/resources/js/pages/users/context
```

### 2. Move Permission Files

```bash
mv resources/js/pages/permissions/index.tsx Modules/Permission/resources/js/pages/permissions/
mv resources/js/pages/permissions/create.tsx Modules/Permission/resources/js/pages/permissions/
mv resources/js/pages/permissions/edit.tsx Modules/Permission/resources/js/pages/permissions/

rmdir resources/js/pages/permissions
```

### 3. Move Role Files

```bash
mv resources/js/pages/roles/index.tsx Modules/Permission/resources/js/pages/roles/
mv resources/js/pages/roles/create.tsx Modules/Permission/resources/js/pages/roles/
mv resources/js/pages/roles/edit.tsx Modules/Permission/resources/js/pages/roles/

rmdir resources/js/pages/roles
```

### 4. Move User Files

```bash
# Main pages
mv resources/js/pages/users/index.tsx Modules/Permission/resources/js/pages/users/
mv resources/js/pages/users/create.tsx Modules/Permission/resources/js/pages/users/
mv resources/js/pages/users/edit.tsx Modules/Permission/resources/js/pages/users/

# Context
mv resources/js/pages/users/context/users-context.tsx Modules/Permission/resources/js/pages/users/context/

# Components
mv resources/js/pages/users/components/*.tsx Modules/Permission/resources/js/pages/users/components/

# Cleanup
rmdir resources/js/pages/users/context
rmdir resources/js/pages/users/components
rmdir resources/js/pages/users
```

### 5. Update Controllers

**PermissionsController.php:**
```php
// Before
return Inertia::render('permissions/index', [...]);
return Inertia::render('permissions/create', [...]);
return Inertia::render('permissions/edit', [...]);

// After
return Inertia::render('Permission::permissions/index', [...]);
return Inertia::render('Permission::permissions/create', [...]);
return Inertia::render('Permission::permissions/edit', [...]);
```

**RolesController.php:**
```php
// Before
return Inertia::render('roles/index', [...]);
return Inertia::render('roles/create', [...]);
return Inertia::render('roles/edit', [...]);

// After
return Inertia::render('Permission::roles/index', [...]);
return Inertia::render('Permission::roles/create', [...]);
return Inertia::render('Permission::roles/edit', [...]);
```

**UsersController.php:**
```php
// Before
return Inertia::render('users/index', [...]);
return Inertia::render('users/create', [...]);
return Inertia::render('users/edit', [...]);

// After
return Inertia::render('Permission::users/index', [...]);
return Inertia::render('Permission::users/create', [...]);
return Inertia::render('Permission::users/edit', [...]);
```

### 6. Test Each Route

```bash
# Permissions
# /permissions
# /permissions/create
# /permissions/{id}/edit

# Roles
# /roles
# /roles/create
# /roles/{id}/edit

# Users
# /users
# /users/create
# /users/{id}/edit
```

## Todo List

### Permissions
- [ ] Create permissions directory
- [ ] Move permissions/index.tsx
- [ ] Move permissions/create.tsx
- [ ] Move permissions/edit.tsx
- [ ] Update PermissionsController.php

### Roles
- [ ] Create roles directory
- [ ] Move roles/index.tsx
- [ ] Move roles/create.tsx
- [ ] Move roles/edit.tsx
- [ ] Update RolesController.php

### Users
- [ ] Create users directory structure
- [ ] Move users/index.tsx
- [ ] Move users/create.tsx
- [ ] Move users/edit.tsx
- [ ] Move users/context/users-context.tsx
- [ ] Move all users components (10 files)
- [ ] Update UsersController.php

### Testing
- [ ] Test all permission routes (3)
- [ ] Test all role routes (3)
- [ ] Test all user routes (3)
- [ ] Verify user table features work
- [ ] Verify delete dialogs work
- [ ] Remove empty source directories (3)

## Success Criteria

1. All 9 main pages load correctly
2. CRUD operations work for permissions, roles, users
3. User data table features work (sort, filter, pagination)
4. Delete dialogs work correctly
5. No TypeScript errors in moved files
6. HMR works for module pages
7. All source directories removed

## Risk Assessment

| Risk | Impact | Mitigation |
|------|--------|------------|
| Multiple source dirs confusing | Medium | Move one section at a time |
| User table breaks | High | Move all user components together |
| Context not working | High | Test context after move |
| Role assignment breaks | Medium | Test role editing with permissions |

## Rollback Plan

If issues arise:
```bash
git checkout -- resources/js/pages/permissions/
git checkout -- resources/js/pages/roles/
git checkout -- resources/js/pages/users/
git checkout -- Modules/Permission/Http/Controllers/
```

## Next Steps

After completion, proceed to Phase 09: Testing & Validation
