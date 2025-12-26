# Phase 03: Module Directory Structure

**Date:** 2025-12-25
**Priority:** P1
**Status:** pending
**Estimated Effort:** 1 hour
**Depends On:** Phase 01, Phase 02

---

## Overview

Create the frontend directory structure within each Laravel module. This prepares the target locations for page migrations.

## Key Insights (from research)

- Follow nwidart/laravel-modules convention: `Modules/{Name}/resources/js/pages/`
- Keep structure consistent across all modules
- Support for components, context, and types per module

## Requirements

- [ ] Create `resources/js/pages/` in each module
- [ ] Create optional subdirs: `components/`, `context/`, `types/`
- [ ] Verify TypeScript can resolve new paths
- [ ] Ensure Vite HMR detects new directories

## Related Code Files

```
/Users/binjuhor/Development/shadcn-admin/Modules/Blog/
/Users/binjuhor/Development/shadcn-admin/Modules/Ecommerce/
/Users/binjuhor/Development/shadcn-admin/Modules/Invoice/
/Users/binjuhor/Development/shadcn-admin/Modules/Notification/
/Users/binjuhor/Development/shadcn-admin/Modules/Permission/
```

## Target Directory Structure

Each module will have:

```
Modules/
├── Blog/
│   ├── resources/
│   │   ├── js/
│   │   │   └── pages/
│   │   │       ├── posts.tsx
│   │   │       ├── post.tsx
│   │   │       ├── create-post.tsx
│   │   │       ├── edit-post.tsx
│   │   │       ├── categories.tsx
│   │   │       ├── create-category.tsx
│   │   │       ├── edit-category.tsx
│   │   │       ├── tags.tsx
│   │   │       ├── create-tag.tsx
│   │   │       └── edit-tag.tsx
│   │   └── views/
│   └── ...
├── Ecommerce/
│   ├── resources/
│   │   ├── js/
│   │   │   └── pages/
│   │   │       ├── products.tsx
│   │   │       ├── product.tsx
│   │   │       ├── create-product.tsx
│   │   │       ├── edit-product.tsx
│   │   │       ├── categories.tsx
│   │   │       ├── create-category.tsx
│   │   │       ├── edit-category.tsx
│   │   │       ├── tags.tsx
│   │   │       ├── create-tag.tsx
│   │   │       ├── edit-tag.tsx
│   │   │       └── orders.tsx
│   │   └── views/
│   └── ...
├── Invoice/
│   ├── resources/
│   │   ├── js/
│   │   │   └── pages/
│   │   │       ├── index.tsx
│   │   │       ├── create.tsx
│   │   │       ├── edit.tsx
│   │   │       ├── show.tsx
│   │   │       ├── context/
│   │   │       │   └── invoices-context.tsx
│   │   │       └── components/
│   │   │           ├── invoice-form.tsx
│   │   │           ├── invoices-table.tsx
│   │   │           ├── invoices-columns.tsx
│   │   │           ├── invoices-dialogs.tsx
│   │   │           ├── invoices-delete-dialog.tsx
│   │   │           ├── invoices-primary-buttons.tsx
│   │   │           ├── invoice-summary.tsx
│   │   │           ├── line-items-input.tsx
│   │   │           ├── data-table-*.tsx
│   │   │           └── ...
│   │   └── views/
│   └── ...
├── Notification/
│   ├── resources/
│   │   ├── js/
│   │   │   └── pages/
│   │   │       ├── index.tsx
│   │   │       ├── send/
│   │   │       │   └── index.tsx
│   │   │       └── templates/
│   │   │           ├── index.tsx
│   │   │           ├── create.tsx
│   │   │           └── edit.tsx
│   │   └── views/
│   └── ...
└── Permission/
    ├── resources/
    │   ├── js/
    │   │   └── pages/
    │   │       ├── permissions/
    │   │       │   ├── index.tsx
    │   │       │   ├── create.tsx
    │   │       │   └── edit.tsx
    │   │       ├── roles/
    │   │       │   ├── index.tsx
    │   │       │   ├── create.tsx
    │   │       │   └── edit.tsx
    │   │       └── users/
    │   │           ├── index.tsx
    │   │           ├── create.tsx
    │   │           ├── edit.tsx
    │   │           ├── context/
    │   │           └── components/
    │   └── views/
    └── ...
```

## Implementation Steps

### 1. Create Directory Structure

```bash
# Blog Module
mkdir -p Modules/Blog/resources/js/pages

# Ecommerce Module
mkdir -p Modules/Ecommerce/resources/js/pages

# Invoice Module
mkdir -p Modules/Invoice/resources/js/pages/components
mkdir -p Modules/Invoice/resources/js/pages/context

# Notification Module
mkdir -p Modules/Notification/resources/js/pages/send
mkdir -p Modules/Notification/resources/js/pages/templates

# Permission Module
mkdir -p Modules/Permission/resources/js/pages/permissions
mkdir -p Modules/Permission/resources/js/pages/roles
mkdir -p Modules/Permission/resources/js/pages/users/components
mkdir -p Modules/Permission/resources/js/pages/users/context
```

### 2. Create Index Files (optional, for cleaner imports)

Each module can have a barrel export:

```typescript
// Modules/Invoice/resources/js/pages/index.ts
export { default as InvoiceIndex } from './index';
export { default as InvoiceCreate } from './create';
export { default as InvoiceEdit } from './edit';
export { default as InvoiceShow } from './show';
```

### 3. Verify TypeScript Recognition

After creating directories, verify:

```bash
# Check TypeScript compiles
npx tsc --noEmit

# Verify paths in IDE show no errors
```

## Todo List

- [ ] Create Blog module frontend directories
- [ ] Create Ecommerce module frontend directories
- [ ] Create Invoice module frontend directories
- [ ] Create Notification module frontend directories
- [ ] Create Permission module frontend directories
- [ ] Verify TypeScript recognizes new paths
- [ ] Run `pnpm run dev` to verify HMR sees new dirs

## Success Criteria

1. All 5 modules have `resources/js/pages/` directory
2. Subdirectories match current page structure in main app
3. TypeScript compiles without errors
4. Vite dev server recognizes new directories

## Risk Assessment

| Risk | Impact | Mitigation |
|------|--------|------------|
| Directory permissions | Low | Use standard mkdir |
| Git ignoring new dirs | Low | Ensure .gitignore correct |
| Vite not detecting | Low | Restart dev server |

## Next Steps

After completion, proceed to Phase 04: Blog Module Migration
