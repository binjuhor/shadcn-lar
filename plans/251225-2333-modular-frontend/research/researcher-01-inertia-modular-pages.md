# Research Report: Inertia.js + Laravel Modular Frontend Architecture

**Date:** 2025-12-25
**Scope:** Page resolution patterns, import.meta.glob configuration, naming conventions for modular Inertia applications

---

## Executive Summary

Inertia.js with modular Laravel requires custom page resolution configuration. Three proven patterns exist: (1) **multi-path glob arrays** for eager discovery, (2) **namespace syntax** (`Module::Page`) for semantic clarity, (3) **package solutions** for batteries-included setup. Best approach depends on module autonomy requirements—monolithic Laravel modules use multi-path globs; independent/installable modules use namespaced resolution. TypeScript path aliases required for clean imports.

---

## Key Findings

### 1. Page Resolution Patterns

**Pattern A: Multi-Path Glob (Recommended for monolithic modules)**
```typescript
// resources/js/app.tsx
createInertiaApp({
  resolve: (name) =>
    resolvePageComponent(
      `./pages/${name}.tsx`,
      import.meta.glob([
        './pages/**/*.tsx',
        '../../modules/**/*.tsx',  // Add modules path
      ]),
    ),
})
```
- **Pros:** Simple, automatic discovery, works with flat directory structures
- **Cons:** Page naming must be consistent across directories (collision risk)
- **Use case:** Feature modules bundled with main app

**Pattern B: Namespace Syntax (Recommended for downloadable modules)**
```typescript
// resources/js/app.tsx
const resolvePageComponent = (name: string) => {
  const [moduleName, ...parts] = name.split('::')
  const pagePath = parts.join('/')

  if (moduleName && parts.length) {
    // Convert PascalCase to kebab-case: Invoice → invoice
    const moduleDir = moduleName.replace(/([A-Z])/g, '-$1').toLowerCase().slice(1)
    return import(
      `../../modules/${moduleDir}/resources/js/Pages/${pagePath}.tsx`
    ).then(m => m.default)
  }

  return import(`./pages/${pagePath}.tsx`).then(m => m.default)
}

// In controller: Inertia::render('Invoice::Index')
// Loads: modules/invoice/resources/js/Pages/Index.tsx
```
- **Pros:** Avoids naming collisions, enables independent modules, semantic clarity
- **Cons:** Requires consistent naming conversion, more complex resolver
- **Use case:** downloadable/composable modules, plugin-like architecture

**Pattern C: Package-Based Solution**
```typescript
// via crmdesenvolvimentos/modules-inertia or toanld/modules-inertia
// Automatically handles resolution with config-based page paths
```
- **Pros:** Zero boilerplate, built-in testing support
- **Cons:** Adds dependency, less flexible
- **Use case:** Teams prioritizing DX over customization

### 2. import.meta.glob Multi-Directory Support

Vite's `import.meta.glob` accepts **array of glob patterns**:
```typescript
const pages = import.meta.glob([
  './pages/**/*.{jsx,tsx}',
  '../../modules/**/resources/js/Pages/**/*.{jsx,tsx}',
  '!**/*.spec.{jsx,tsx}', // negative patterns exclude files
])
```

**Key Constraints:**
- Patterns MUST be string literals (no variables/interpolation)
- Can start with `./` (relative), `/` (root), or alias paths
- All globs resolved at build time → no runtime path construction
- For namespace syntax, use dynamic `import()` instead of glob patterns

**Eager Loading Option:**
```typescript
// Loads all modules at bundle init (use sparingly)
import.meta.glob('...', { eager: true })
```

### 3. Module Directory Structures

**Standard Laravel-Modules (nwidart) layout:**
```
modules/
├── Invoice/
│   ├── resources/
│   │   └── js/
│   │       └── Pages/
│   │           ├── Index.tsx
│   │           └── Show.tsx
│   ├── Http/Controllers/
│   ├── routes/web.php
│   └── module.json
├── Settings/
└── Blog/
```

**Alternative (InterNACHI Modular):**
```
app-modules/
├── invoice/
│   └── resources/js/Pages/
└── settings/
```

### 4. TypeScript Path Aliases for Clean Imports

```json
// tsconfig.json / vite.config.ts
{
  "compilerOptions": {
    "paths": {
      "@//*": ["./resources/js/*"],
      "@modules/*": ["./modules/*/resources/js/*"],
      "@invoice/*": ["./modules/Invoice/resources/js/*"]
    }
  }
}
```

Enables: `import { Form } from '@invoice/components'` instead of relative imports.

### 5. Testing Configuration

```php
// config/inertia.php
'testing' => [
    'ensure_pages_exist' => true,
    'page_paths' => [
        'resources/js/Pages',
        'modules',  // Add modules directory
    ],
],
```

Without this, test assertions like `$response->assertInertia('Page')` fail for modular pages.

---

## Naming Conventions Comparison

| Approach | Example | Pros | Cons |
|----------|---------|------|------|
| **Path-based** | `pages/invoices/Index.tsx` | Simple, flat structure | Naming collisions across modules |
| **Directory-namespaced** | `invoices/pages/Index.tsx` | Module ownership clear | Deeper nesting |
| **Semantic namespace** | `Invoice::Index` | No collisions, explicit | Requires resolver logic |
| **Package (toanld)** | Auto-resolved | Zero config | Less flexible, added dep |

**Recommendation:** Use semantic namespace (`Module::Page`) for independent modules; multi-path glob for bundled features.

---

## Common Gotchas & Solutions

| Issue | Cause | Solution |
|-------|-------|----------|
| "Cannot find module" | Glob pattern doesn't match directory | Verify relative paths start with `./` or absolute from project root |
| Hot reload fails in dev | Module pages not included in Vite input | Add modules to `vite.config.js` input or use `refresh: true` |
| Test assertions fail | `page_paths` config missing module dir | Update `config/inertia.php` with modules path |
| Import resolution in modules | Missing path aliases | Configure `tsconfig.json` aliases for module directories |
| Namespace not working | Case mismatch in conversion logic | Ensure module name case conversion matches file structure |

---

## Implementation Checklist

For **Pattern B (Namespace Syntax, Recommended):**

- [ ] Configure `vite.config.js` to include module entry if needed
- [ ] Update `resources/js/app.tsx` with namespace-aware resolver
- [ ] Create `tsconfig.json` path aliases for module imports
- [ ] Update `config/inertia.php` with module page paths
- [ ] Test `Inertia::render('Module::Page')` from controllers
- [ ] Verify hot reload works: `npm run dev`
- [ ] Run `php artisan inertia:test-views` if using assertions

---

## Sources

- [Inertia.js & React with Modular Laravel - Canned Atropine](https://igeek.info/2024/using-inertia-js-react-with-modular-laravel/)
- [Using Inertia in Modules - Matt K](https://mattk.ing/posts/2024-08-30-using-inertia-in-modules/)
- [Vite Glob Imports Documentation](https://vite.dev/guide/features)
- [nWidart Laravel Modules Discussion #1162](https://github.com/nWidart/laravel-modules/issues/1162)
- [crmdesenvolvimentos/modules-inertia Package](https://packagist.org/packages/crmdesenvolvimentos/modules-inertia)
- [Vite Workshop - Glob Import](https://vite-workshop.vercel.app/glob-import)

---

## Unresolved Questions

- How to handle TypeScript inference for dynamically imported module components?
- Best strategy for code-splitting with modular architecture (eager vs lazy loading)?
- How to validate module page existence at build time (e.g., prevent broken `Module::Page` references)?
