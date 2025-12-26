# Phase 06: Invoice Module Migration

**Date:** 2025-12-25
**Priority:** P1
**Status:** pending
**Estimated Effort:** 3-4 hours
**Depends On:** Phase 03

---

## Overview

Migrate Invoice module frontend pages from `resources/js/pages/invoices/` to `Modules/Invoice/resources/js/pages/`. This is the most complex migration due to nested components and context.

## Files to Migrate

### Main Pages (4 files)
| Source | Target | Controller Change |
|--------|--------|-------------------|
| `invoices/index.tsx` | `Invoice/pages/index.tsx` | `Invoice::index` |
| `invoices/create.tsx` | `Invoice/pages/create.tsx` | `Invoice::create` |
| `invoices/edit.tsx` | `Invoice/pages/edit.tsx` | `Invoice::edit` |
| `invoices/show.tsx` | `Invoice/pages/show.tsx` | `Invoice::show` |

### Context (1 file)
| Source | Target |
|--------|--------|
| `invoices/context/invoices-context.tsx` | `Invoice/pages/context/invoices-context.tsx` |

### Components (13 files)
| Source | Target |
|--------|--------|
| `invoices/components/invoice-form.tsx` | `Invoice/pages/components/invoice-form.tsx` |
| `invoices/components/invoices-table.tsx` | `Invoice/pages/components/invoices-table.tsx` |
| `invoices/components/invoices-columns.tsx` | `Invoice/pages/components/invoices-columns.tsx` |
| `invoices/components/invoices-dialogs.tsx` | `Invoice/pages/components/invoices-dialogs.tsx` |
| `invoices/components/invoices-delete-dialog.tsx` | `Invoice/pages/components/invoices-delete-dialog.tsx` |
| `invoices/components/invoices-primary-buttons.tsx` | `Invoice/pages/components/invoices-primary-buttons.tsx` |
| `invoices/components/invoice-summary.tsx` | `Invoice/pages/components/invoice-summary.tsx` |
| `invoices/components/line-items-input.tsx` | `Invoice/pages/components/line-items-input.tsx` |
| `invoices/components/data-table-column-header.tsx` | `Invoice/pages/components/data-table-column-header.tsx` |
| `invoices/components/data-table-faceted-filter.tsx` | `Invoice/pages/components/data-table-faceted-filter.tsx` |
| `invoices/components/data-table-pagination.tsx` | `Invoice/pages/components/data-table-pagination.tsx` |
| `invoices/components/data-table-row-actions.tsx` | `Invoice/pages/components/data-table-row-actions.tsx` |
| `invoices/components/data-table-toolbar.tsx` | `Invoice/pages/components/data-table-toolbar.tsx` |

**Total: 18 files**

## Related Code Files

```
# Source pages
/Users/binjuhor/Development/shadcn-admin/resources/js/pages/invoices/index.tsx
/Users/binjuhor/Development/shadcn-admin/resources/js/pages/invoices/create.tsx
/Users/binjuhor/Development/shadcn-admin/resources/js/pages/invoices/edit.tsx
/Users/binjuhor/Development/shadcn-admin/resources/js/pages/invoices/show.tsx
/Users/binjuhor/Development/shadcn-admin/resources/js/pages/invoices/context/invoices-context.tsx
/Users/binjuhor/Development/shadcn-admin/resources/js/pages/invoices/components/*.tsx (13 files)

# Controllers to update
/Users/binjuhor/Development/shadcn-admin/Modules/Invoice/Http/Controllers/InvoicesController.php
```

## Implementation Steps

### 1. Create Directory Structure

```bash
mkdir -p Modules/Invoice/resources/js/pages/components
mkdir -p Modules/Invoice/resources/js/pages/context
```

### 2. Move Page Files

```bash
# Main pages
mv resources/js/pages/invoices/index.tsx Modules/Invoice/resources/js/pages/
mv resources/js/pages/invoices/create.tsx Modules/Invoice/resources/js/pages/
mv resources/js/pages/invoices/edit.tsx Modules/Invoice/resources/js/pages/
mv resources/js/pages/invoices/show.tsx Modules/Invoice/resources/js/pages/

# Context
mv resources/js/pages/invoices/context/invoices-context.tsx Modules/Invoice/resources/js/pages/context/

# Components
mv resources/js/pages/invoices/components/*.tsx Modules/Invoice/resources/js/pages/components/

# Remove empty directories
rmdir resources/js/pages/invoices/context
rmdir resources/js/pages/invoices/components
rmdir resources/js/pages/invoices
```

### 3. Update Import Paths

**Key Import Changes:**

Components importing from shared `@/components/ui/*` remain unchanged.

Internal imports change from:
```typescript
// Before
import { InvoicesContext } from './context/invoices-context';
import { InvoicesTable } from './components/invoices-table';

// After - same relative paths (structure preserved)
import { InvoicesContext } from './context/invoices-context';
import { InvoicesTable } from './components/invoices-table';
```

### 4. Update InvoicesController.php

```php
// Before
return Inertia::render('invoices/index', [...]);
return Inertia::render('invoices/create', [...]);
return Inertia::render('invoices/edit', [...]);
return Inertia::render('invoices/show', [...]);

// After
return Inertia::render('Invoice::index', [...]);
return Inertia::render('Invoice::create', [...]);
return Inertia::render('Invoice::edit', [...]);
return Inertia::render('Invoice::show', [...]);
```

### 5. Test Each Route

```bash
# Test routes in browser
# /invoices
# /invoices/create
# /invoices/{id}
# /invoices/{id}/edit
```

### 6. Test Specific Functionality

- [ ] Invoice list displays with pagination
- [ ] Invoice creation with line items
- [ ] Invoice editing
- [ ] Invoice view/show page
- [ ] Delete dialog works
- [ ] PDF generation (if applicable)

## Todo List

- [ ] Create Invoice module pages directory structure
- [ ] Move index.tsx and update imports
- [ ] Move create.tsx and update imports
- [ ] Move edit.tsx and update imports
- [ ] Move show.tsx and update imports
- [ ] Move context/invoices-context.tsx
- [ ] Move all components (13 files)
- [ ] Update InvoicesController.php
- [ ] Test invoice list route
- [ ] Test invoice create route
- [ ] Test invoice edit route
- [ ] Test invoice show route
- [ ] Verify line items input works
- [ ] Verify delete dialog works
- [ ] Remove empty source directories

## Success Criteria

1. All 4 invoice pages load correctly
2. CRUD operations work (create, read, update, delete)
3. Line items input works in forms
4. Invoice summary displays correctly
5. Data table features work (sort, filter, pagination)
6. No TypeScript errors in moved files
7. HMR works for module pages

## Risk Assessment

| Risk | Impact | Mitigation |
|------|--------|------------|
| Broken component imports | High | Move all components together, preserve structure |
| Context not working | High | Test context provider after move |
| Data table breaks | Medium | Test all table features |
| Line items not saving | Medium | Test form submission thoroughly |

## Rollback Plan

If issues arise:
```bash
git checkout -- resources/js/pages/invoices/
git checkout -- Modules/Invoice/Http/Controllers/
```

## Next Steps

After completion, proceed to Phase 07: Notification Module Migration
