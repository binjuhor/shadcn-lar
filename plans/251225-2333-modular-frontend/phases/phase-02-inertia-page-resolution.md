# Phase 02: Inertia Page Resolution

**Date:** 2025-12-25
**Priority:** P0 (Critical Path)
**Status:** pending
**Estimated Effort:** 2-3 hours
**Depends On:** Phase 01

---

## Overview

Implement namespace-aware page resolution in Inertia.js to support `Module::page/path` syntax. Controllers can then use `Inertia::render('Invoice::index')` to load module pages.

## Key Insights (from research)

- Use `Module::PagePath` syntax for semantic clarity and collision avoidance
- Dynamic `import()` required for namespace resolution (not glob patterns)
- Convert PascalCase module names to directory paths
- Fallback to main app pages if no namespace prefix

## Requirements

- [ ] Create namespace-aware page resolver in `app.tsx`
- [ ] Support both namespaced (`Module::page`) and non-namespaced pages
- [ ] Handle PascalCase to directory path conversion
- [ ] Update `config/inertia.php` for test assertions
- [ ] Maintain backward compatibility with existing pages

## Related Code Files

```
/Users/binjuhor/Development/shadcn-admin/resources/js/app.tsx
/Users/binjuhor/Development/shadcn-admin/config/inertia.php
```

## Implementation Steps

### 1. Update resources/js/app.tsx

Replace the current resolver with namespace-aware version:

```typescript
import '../css/app.css';
import './bootstrap';

import React, { StrictMode } from 'react';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { AppLayout } from './layouts';
import { Providers } from './providers';

const appName = import.meta.env.VITE_APP_NAME || 'Shadcn Laravel Admin';

// Glob patterns for page discovery
const mainPages = import.meta.glob('./pages/**/*.tsx');
const modulePages = import.meta.glob('../../Modules/*/resources/js/pages/**/*.tsx');

/**
 * Resolve Inertia page component with namespace support
 *
 * Supports two formats:
 * - 'page/path' - resolves from resources/js/pages/
 * - 'Module::page/path' - resolves from Modules/{Module}/resources/js/pages/
 *
 * @example
 * Inertia::render('Dashboard')           -> ./pages/Dashboard.tsx
 * Inertia::render('invoices/index')      -> ./pages/invoices/index.tsx
 * Inertia::render('Invoice::index')      -> Modules/Invoice/resources/js/pages/index.tsx
 * Inertia::render('Blog::posts/create')  -> Modules/Blog/resources/js/pages/posts/create.tsx
 */
async function resolvePageComponent(name: string): Promise<React.ComponentType> {
  // Check for namespace syntax (Module::PagePath)
  if (name.includes('::')) {
    const [moduleName, pagePath] = name.split('::');
    const modulePath = `../../Modules/${moduleName}/resources/js/pages/${pagePath}.tsx`;

    const page = modulePages[modulePath];
    if (!page) {
      throw new Error(
        `Module page not found: ${name}\n` +
        `Expected path: Modules/${moduleName}/resources/js/pages/${pagePath}.tsx`
      );
    }

    const module = await page();
    return (module as { default: React.ComponentType }).default;
  }

  // Standard page resolution (main app)
  const pagePath = `./pages/${name}.tsx`;
  const page = mainPages[pagePath];

  if (!page) {
    throw new Error(
      `Page not found: ${name}\n` +
      `Expected path: resources/js/pages/${name}.tsx`
    );
  }

  const module = await page();
  return (module as { default: React.ComponentType }).default;
}

createInertiaApp({
  title: (title) => `${title} - ${appName}`,
  resolve: resolvePageComponent,
  setup({ el, App, props }) {
    const root = createRoot(el);

    root.render(
      <StrictMode>
        <Providers>
          <AppLayout>
            <App {...props} />
          </AppLayout>
        </Providers>
      </StrictMode>
    );
  },
  progress: {
    color: '#4B5563',
  },
});
```

### 2. Update config/inertia.php (if exists)

Add module paths for test assertions:

```php
<?php

return [
    'testing' => [
        'ensure_pages_exist' => true,
        'page_paths' => [
            resource_path('js/pages'),
            base_path('Modules/Blog/resources/js/pages'),
            base_path('Modules/Ecommerce/resources/js/pages'),
            base_path('Modules/Invoice/resources/js/pages'),
            base_path('Modules/Notification/resources/js/pages'),
            base_path('Modules/Permission/resources/js/pages'),
        ],
        'page_extensions' => [
            'tsx',
        ],
    ],
];
```

### 3. Create Helper Type Definition (optional but recommended)

Add type for better IDE support:

```typescript
// resources/js/types/inertia.d.ts
declare module '@inertiajs/react' {
  interface PageProps {
    // Add shared page props here
  }
}

// Module page path types (for autocomplete)
type ModulePagePath =
  | `Blog::${string}`
  | `Ecommerce::${string}`
  | `Invoice::${string}`
  | `Notification::${string}`
  | `Permission::${string}`;
```

### 4. Test Resolution

Create a temporary test page to verify:

```bash
# Create test module page structure
mkdir -p Modules/Invoice/resources/js/pages

# Create minimal test page
cat > Modules/Invoice/resources/js/pages/test.tsx << 'EOF'
export default function TestPage() {
  return <div>Invoice Module Test Page</div>;
}
EOF
```

Update a controller temporarily:
```php
// Test route
Route::get('/test-module-page', function () {
    return Inertia::render('Invoice::test');
});
```

## Todo List

- [ ] Backup current `app.tsx`
- [ ] Implement namespace-aware resolver
- [ ] Add both main and module glob patterns
- [ ] Create/update `config/inertia.php`
- [ ] Create test page in Invoice module
- [ ] Add temporary test route
- [ ] Verify page loads via browser
- [ ] Remove test page and route
- [ ] Run full test suite

## Success Criteria

1. `Module::page` syntax resolves correctly
2. Non-namespaced pages still work (backward compatible)
3. Error messages are clear when page not found
4. TypeScript shows no type errors
5. HMR works for module pages

## Risk Assessment

| Risk | Impact | Mitigation |
|------|--------|------------|
| Existing pages break | High | Test all current pages after change |
| Glob patterns don't match | High | Verify paths match directory structure |
| SSR compatibility (if used) | Medium | Test SSR if enabled |
| Import path case sensitivity | Low | Use exact PascalCase matching |

## Controller Usage Examples

After migration, controllers will use:

```php
// Before (current)
return Inertia::render('invoices/index');

// After (namespaced)
return Inertia::render('Invoice::index');

// Nested pages
return Inertia::render('Blog::posts/create');
return Inertia::render('Permission::users/edit');
```

## Next Steps

After completion, proceed to Phase 03: Module Directory Structure
