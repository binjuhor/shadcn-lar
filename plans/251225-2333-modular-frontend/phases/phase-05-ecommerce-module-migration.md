# Phase 05: Ecommerce Module Migration

**Date:** 2025-12-25
**Priority:** P1
**Status:** pending
**Estimated Effort:** 2-3 hours
**Depends On:** Phase 03

---

## Overview

Migrate Ecommerce module frontend pages from `resources/js/pages/ecommerce/` to `Modules/Ecommerce/resources/js/pages/`. Update Laravel controllers to use namespaced page paths.

## Files to Migrate

| Source | Target | Controller Change |
|--------|--------|-------------------|
| `resources/js/pages/ecommerce/products.tsx` | `Modules/Ecommerce/resources/js/pages/products.tsx` | `Ecommerce::products` |
| `resources/js/pages/ecommerce/product.tsx` | `Modules/Ecommerce/resources/js/pages/product.tsx` | `Ecommerce::product` |
| `resources/js/pages/ecommerce/create-product.tsx` | `Modules/Ecommerce/resources/js/pages/create-product.tsx` | `Ecommerce::create-product` |
| `resources/js/pages/ecommerce/edit-product.tsx` | `Modules/Ecommerce/resources/js/pages/edit-product.tsx` | `Ecommerce::edit-product` |
| `resources/js/pages/ecommerce/categories.tsx` | `Modules/Ecommerce/resources/js/pages/categories.tsx` | `Ecommerce::categories` |
| `resources/js/pages/ecommerce/create-category.tsx` | `Modules/Ecommerce/resources/js/pages/create-category.tsx` | `Ecommerce::create-category` |
| `resources/js/pages/ecommerce/edit-category.tsx` | `Modules/Ecommerce/resources/js/pages/edit-category.tsx` | `Ecommerce::edit-category` |
| `resources/js/pages/ecommerce/tags.tsx` | `Modules/Ecommerce/resources/js/pages/tags.tsx` | `Ecommerce::tags` |
| `resources/js/pages/ecommerce/create-tag.tsx` | `Modules/Ecommerce/resources/js/pages/create-tag.tsx` | `Ecommerce::create-tag` |
| `resources/js/pages/ecommerce/edit-tag.tsx` | `Modules/Ecommerce/resources/js/pages/edit-tag.tsx` | `Ecommerce::edit-tag` |
| `resources/js/pages/ecommerce/orders.tsx` | `Modules/Ecommerce/resources/js/pages/orders.tsx` | `Ecommerce::orders` |

**Total: 11 files**

## Related Code Files

```
# Source pages
/Users/binjuhor/Development/shadcn-admin/resources/js/pages/ecommerce/products.tsx
/Users/binjuhor/Development/shadcn-admin/resources/js/pages/ecommerce/product.tsx
/Users/binjuhor/Development/shadcn-admin/resources/js/pages/ecommerce/create-product.tsx
/Users/binjuhor/Development/shadcn-admin/resources/js/pages/ecommerce/edit-product.tsx
/Users/binjuhor/Development/shadcn-admin/resources/js/pages/ecommerce/categories.tsx
/Users/binjuhor/Development/shadcn-admin/resources/js/pages/ecommerce/create-category.tsx
/Users/binjuhor/Development/shadcn-admin/resources/js/pages/ecommerce/edit-category.tsx
/Users/binjuhor/Development/shadcn-admin/resources/js/pages/ecommerce/tags.tsx
/Users/binjuhor/Development/shadcn-admin/resources/js/pages/ecommerce/create-tag.tsx
/Users/binjuhor/Development/shadcn-admin/resources/js/pages/ecommerce/edit-tag.tsx
/Users/binjuhor/Development/shadcn-admin/resources/js/pages/ecommerce/orders.tsx

# Controllers to update
/Users/binjuhor/Development/shadcn-admin/Modules/Ecommerce/Http/Controllers/ProductsController.php
/Users/binjuhor/Development/shadcn-admin/Modules/Ecommerce/Http/Controllers/CategoriesController.php
/Users/binjuhor/Development/shadcn-admin/Modules/Ecommerce/Http/Controllers/TagsController.php
/Users/binjuhor/Development/shadcn-admin/Modules/Ecommerce/Http/Controllers/OrdersController.php
```

## Implementation Steps

### 1. Move Page Files

```bash
# Create target directory
mkdir -p Modules/Ecommerce/resources/js/pages

# Move files
mv resources/js/pages/ecommerce/products.tsx Modules/Ecommerce/resources/js/pages/
mv resources/js/pages/ecommerce/product.tsx Modules/Ecommerce/resources/js/pages/
mv resources/js/pages/ecommerce/create-product.tsx Modules/Ecommerce/resources/js/pages/
mv resources/js/pages/ecommerce/edit-product.tsx Modules/Ecommerce/resources/js/pages/
mv resources/js/pages/ecommerce/categories.tsx Modules/Ecommerce/resources/js/pages/
mv resources/js/pages/ecommerce/create-category.tsx Modules/Ecommerce/resources/js/pages/
mv resources/js/pages/ecommerce/edit-category.tsx Modules/Ecommerce/resources/js/pages/
mv resources/js/pages/ecommerce/tags.tsx Modules/Ecommerce/resources/js/pages/
mv resources/js/pages/ecommerce/create-tag.tsx Modules/Ecommerce/resources/js/pages/
mv resources/js/pages/ecommerce/edit-tag.tsx Modules/Ecommerce/resources/js/pages/
mv resources/js/pages/ecommerce/orders.tsx Modules/Ecommerce/resources/js/pages/

# Remove empty source directory
rmdir resources/js/pages/ecommerce
```

### 2. Update Import Paths in Pages

Each moved file needs import path updates. Shared components via `@/` remain unchanged.

### 3. Update Controllers

**ProductsController.php:**
```php
// Before
return Inertia::render('ecommerce/products', [...]);
return Inertia::render('ecommerce/product', [...]);
return Inertia::render('ecommerce/create-product', [...]);
return Inertia::render('ecommerce/edit-product', [...]);

// After
return Inertia::render('Ecommerce::products', [...]);
return Inertia::render('Ecommerce::product', [...]);
return Inertia::render('Ecommerce::create-product', [...]);
return Inertia::render('Ecommerce::edit-product', [...]);
```

**CategoriesController.php:**
```php
// Before
return Inertia::render('ecommerce/categories', [...]);
return Inertia::render('ecommerce/create-category', [...]);
return Inertia::render('ecommerce/edit-category', [...]);

// After
return Inertia::render('Ecommerce::categories', [...]);
return Inertia::render('Ecommerce::create-category', [...]);
return Inertia::render('Ecommerce::edit-category', [...]);
```

**TagsController.php:**
```php
// Before
return Inertia::render('ecommerce/tags', [...]);
return Inertia::render('ecommerce/create-tag', [...]);
return Inertia::render('ecommerce/edit-tag', [...]);

// After
return Inertia::render('Ecommerce::tags', [...]);
return Inertia::render('Ecommerce::create-tag', [...]);
return Inertia::render('Ecommerce::edit-tag', [...]);
```

**OrdersController.php:**
```php
// Before
return Inertia::render('ecommerce/orders', [...]);

// After
return Inertia::render('Ecommerce::orders', [...]);
```

### 4. Test Each Route

```bash
# Test routes in browser
# /ecommerce/products
# /ecommerce/products/create
# /ecommerce/products/{id}
# /ecommerce/products/{id}/edit
# /ecommerce/categories
# /ecommerce/categories/create
# /ecommerce/categories/{id}/edit
# /ecommerce/tags
# /ecommerce/tags/create
# /ecommerce/tags/{id}/edit
# /ecommerce/orders
```

## Todo List

- [ ] Create Ecommerce module pages directory
- [ ] Move products.tsx and update imports
- [ ] Move product.tsx and update imports
- [ ] Move create-product.tsx and update imports
- [ ] Move edit-product.tsx and update imports
- [ ] Move categories.tsx and update imports
- [ ] Move create-category.tsx and update imports
- [ ] Move edit-category.tsx and update imports
- [ ] Move tags.tsx and update imports
- [ ] Move create-tag.tsx and update imports
- [ ] Move edit-tag.tsx and update imports
- [ ] Move orders.tsx and update imports
- [ ] Update ProductsController.php
- [ ] Update CategoriesController.php
- [ ] Update TagsController.php
- [ ] Update OrdersController.php
- [ ] Test all ecommerce routes
- [ ] Remove empty source directory

## Success Criteria

1. All 11 ecommerce pages load correctly
2. CRUD operations work for products, categories, tags
3. Orders listing works
4. No TypeScript errors in moved files
5. HMR works for module pages
6. Original `/resources/js/pages/ecommerce/` removed

## Risk Assessment

| Risk | Impact | Mitigation |
|------|--------|------------|
| Broken imports | High | Check each file's imports after move |
| Missing components | Medium | Verify shared components accessible via @/ |
| Route mismatch | Medium | Test each route in browser |
| Data not loading | Medium | Check controller props passed correctly |

## Rollback Plan

If issues arise:
```bash
git checkout -- resources/js/pages/ecommerce/
git checkout -- Modules/Ecommerce/Http/Controllers/
```

## Next Steps

After completion, proceed to Phase 06: Invoice Module Migration
