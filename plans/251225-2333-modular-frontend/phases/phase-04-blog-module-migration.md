# Phase 04: Blog Module Migration

**Date:** 2025-12-25
**Priority:** P1
**Status:** pending
**Estimated Effort:** 2-3 hours
**Depends On:** Phase 03

---

## Overview

Migrate Blog module frontend pages from `resources/js/pages/blog/` to `Modules/Blog/resources/js/pages/`. Update Laravel controllers to use namespaced page paths.

## Files to Migrate

| Source | Target | Controller Change |
|--------|--------|-------------------|
| `resources/js/pages/blog/posts.tsx` | `Modules/Blog/resources/js/pages/posts.tsx` | `Blog::posts` |
| `resources/js/pages/blog/post.tsx` | `Modules/Blog/resources/js/pages/post.tsx` | `Blog::post` |
| `resources/js/pages/blog/create-post.tsx` | `Modules/Blog/resources/js/pages/create-post.tsx` | `Blog::create-post` |
| `resources/js/pages/blog/edit-post.tsx` | `Modules/Blog/resources/js/pages/edit-post.tsx` | `Blog::edit-post` |
| `resources/js/pages/blog/categories.tsx` | `Modules/Blog/resources/js/pages/categories.tsx` | `Blog::categories` |
| `resources/js/pages/blog/create-category.tsx` | `Modules/Blog/resources/js/pages/create-category.tsx` | `Blog::create-category` |
| `resources/js/pages/blog/edit-category.tsx` | `Modules/Blog/resources/js/pages/edit-category.tsx` | `Blog::edit-category` |
| `resources/js/pages/blog/tags.tsx` | `Modules/Blog/resources/js/pages/tags.tsx` | `Blog::tags` |
| `resources/js/pages/blog/create-tag.tsx` | `Modules/Blog/resources/js/pages/create-tag.tsx` | `Blog::create-tag` |
| `resources/js/pages/blog/edit-tag.tsx` | `Modules/Blog/resources/js/pages/edit-tag.tsx` | `Blog::edit-tag` |

**Total: 10 files**

## Related Code Files

```
# Source pages
/Users/binjuhor/Development/shadcn-admin/resources/js/pages/blog/posts.tsx
/Users/binjuhor/Development/shadcn-admin/resources/js/pages/blog/post.tsx
/Users/binjuhor/Development/shadcn-admin/resources/js/pages/blog/create-post.tsx
/Users/binjuhor/Development/shadcn-admin/resources/js/pages/blog/edit-post.tsx
/Users/binjuhor/Development/shadcn-admin/resources/js/pages/blog/categories.tsx
/Users/binjuhor/Development/shadcn-admin/resources/js/pages/blog/create-category.tsx
/Users/binjuhor/Development/shadcn-admin/resources/js/pages/blog/edit-category.tsx
/Users/binjuhor/Development/shadcn-admin/resources/js/pages/blog/tags.tsx
/Users/binjuhor/Development/shadcn-admin/resources/js/pages/blog/create-tag.tsx
/Users/binjuhor/Development/shadcn-admin/resources/js/pages/blog/edit-tag.tsx

# Controllers to update
/Users/binjuhor/Development/shadcn-admin/Modules/Blog/Http/Controllers/PostsController.php
/Users/binjuhor/Development/shadcn-admin/Modules/Blog/Http/Controllers/CategoriesController.php
/Users/binjuhor/Development/shadcn-admin/Modules/Blog/Http/Controllers/TagsController.php
```

## Implementation Steps

### 1. Move Page Files

```bash
# Create target directory
mkdir -p Modules/Blog/resources/js/pages

# Move files
mv resources/js/pages/blog/posts.tsx Modules/Blog/resources/js/pages/
mv resources/js/pages/blog/post.tsx Modules/Blog/resources/js/pages/
mv resources/js/pages/blog/create-post.tsx Modules/Blog/resources/js/pages/
mv resources/js/pages/blog/edit-post.tsx Modules/Blog/resources/js/pages/
mv resources/js/pages/blog/categories.tsx Modules/Blog/resources/js/pages/
mv resources/js/pages/blog/create-category.tsx Modules/Blog/resources/js/pages/
mv resources/js/pages/blog/edit-category.tsx Modules/Blog/resources/js/pages/
mv resources/js/pages/blog/tags.tsx Modules/Blog/resources/js/pages/
mv resources/js/pages/blog/create-tag.tsx Modules/Blog/resources/js/pages/
mv resources/js/pages/blog/edit-tag.tsx Modules/Blog/resources/js/pages/

# Remove empty source directory
rmdir resources/js/pages/blog
```

### 2. Update Import Paths in Pages

Each moved file needs import path updates. Example for `posts.tsx`:

```typescript
// Before
import { DataTable } from '@/components/ui/data-table';
import { columns } from './components/columns';

// After - shared components unchanged, relative paths adjusted
import { DataTable } from '@/components/ui/data-table';
// If components exist in module, use relative path or @modules alias
```

### 3. Update Controllers

**PostsController.php:**
```php
// Before
return Inertia::render('blog/posts', [...]);
return Inertia::render('blog/post', [...]);
return Inertia::render('blog/create-post', [...]);
return Inertia::render('blog/edit-post', [...]);

// After
return Inertia::render('Blog::posts', [...]);
return Inertia::render('Blog::post', [...]);
return Inertia::render('Blog::create-post', [...]);
return Inertia::render('Blog::edit-post', [...]);
```

**CategoriesController.php:**
```php
// Before
return Inertia::render('blog/categories', [...]);
return Inertia::render('blog/create-category', [...]);
return Inertia::render('blog/edit-category', [...]);

// After
return Inertia::render('Blog::categories', [...]);
return Inertia::render('Blog::create-category', [...]);
return Inertia::render('Blog::edit-category', [...]);
```

**TagsController.php:**
```php
// Before
return Inertia::render('blog/tags', [...]);
return Inertia::render('blog/create-tag', [...]);
return Inertia::render('blog/edit-tag', [...]);

// After
return Inertia::render('Blog::tags', [...]);
return Inertia::render('Blog::create-tag', [...]);
return Inertia::render('Blog::edit-tag', [...]);
```

### 4. Test Each Route

```bash
# Start dev server
pnpm run dev

# Test routes in browser
# /blog/posts
# /blog/posts/create
# /blog/posts/{id}
# /blog/posts/{id}/edit
# /blog/categories
# /blog/categories/create
# /blog/categories/{id}/edit
# /blog/tags
# /blog/tags/create
# /blog/tags/{id}/edit
```

## Todo List

- [ ] Create Blog module pages directory
- [ ] Move posts.tsx and update imports
- [ ] Move post.tsx and update imports
- [ ] Move create-post.tsx and update imports
- [ ] Move edit-post.tsx and update imports
- [ ] Move categories.tsx and update imports
- [ ] Move create-category.tsx and update imports
- [ ] Move edit-category.tsx and update imports
- [ ] Move tags.tsx and update imports
- [ ] Move create-tag.tsx and update imports
- [ ] Move edit-tag.tsx and update imports
- [ ] Update PostsController.php
- [ ] Update CategoriesController.php
- [ ] Update TagsController.php
- [ ] Test all blog routes
- [ ] Remove empty source directory

## Success Criteria

1. All 10 blog pages load correctly
2. CRUD operations work (create, read, update, delete)
3. No TypeScript errors in moved files
4. HMR works for module pages
5. Original `/resources/js/pages/blog/` removed

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
# Restore from git
git checkout -- resources/js/pages/blog/
git checkout -- Modules/Blog/Http/Controllers/
```

## Next Steps

After completion, proceed to Phase 05: Ecommerce Module Migration
