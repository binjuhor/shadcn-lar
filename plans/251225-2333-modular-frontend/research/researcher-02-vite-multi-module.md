# Research: Vite Configuration for Multi-Module Laravel Applications

**Date:** 2025-12-25
**Status:** Complete
**Scope:** Multiple entry points, shared dependencies, build strategies, HMR

---

## 1. Multiple Entry Points Configuration

### Single Vite Config with Array Input
```javascript
// vite.config.js (Root)
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [
    laravel({
      input: [
        'resources/js/app.tsx',           // Main app
        'Modules/Blog/resources/js/app.js',
        'Modules/Invoice/resources/js/app.js',
        'Modules/Notification/resources/js/app.js',
      ],
      refresh: true,
    }),
    react(),
  ],
});
```

**Advantage:** Single build process, automatic code splitting.
**Disadvantage:** Single manifest, all modules rebuild on any change.

### Multiple Config Files (Per-Module Isolation)
```javascript
// Root package.json
"scripts": {
  "dev": "vite",
  "dev:modules": "concurrently \"npm run dev:blog\" \"npm run dev:invoice\"",
  "dev:blog": "vite --config Modules/Blog/vite.config.js",
  "dev:invoice": "vite --config Modules/Invoice/vite.config.js",
  "build": "vite build && npm run build:modules",
  "build:blog": "vite build --config Modules/Blog/vite.config.js",
  "build:invoice": "vite build --config Modules/Invoice/vite.config.js",
}
```

Each module config:
```javascript
// Modules/Blog/vite.config.js
export default defineConfig({
  build: {
    outDir: '../../public/build-blog',
    manifest: true,
  },
  plugins: [
    laravel({
      publicDirectory: '../../public',
      buildDirectory: 'build-blog',
      hotFile: '../../public/blog.hot',
      input: ['./resources/assets/js/app.js'],
      refresh: true,
    }),
  ],
});
```

**Advantage:** Independent builds, better cache isolation, per-module HMR files.
**Disadvantage:** Manual concurrency, multiple manifest files.

---

## 2. Shared Dependencies Strategy

### Approach A: Rollup Manual Chunks
```javascript
// vite.config.js
export default defineConfig({
  build: {
    rollupOptions: {
      output: {
        manualChunks(id) {
          // Share vendor libs across all modules
          if (id.includes('node_modules')) {
            if (id.includes('react') || id.includes('react-dom')) {
              return 'vendor-react';
            }
            return 'vendor-shared';
          }
          // Keep module-specific code separate
          if (id.includes('/Modules/')) {
            const match = id.match(/\/Modules\/(\w+)\//);
            return match ? `module-${match[1]}` : 'modules';
          }
        },
      },
    },
  },
});
```

**Result:**
- `vendor-react.js` - Shared across all modules
- `vendor-shared.js` - Common deps (lodash, axios, etc.)
- `module-blog.js` - Blog-specific code
- `module-invoice.js` - Invoice-specific code

### Approach B: Symlink/Alias Shared Components
```json
{
  "compilerOptions": {
    "paths": {
      "@/*": ["./resources/js/*"],
      "@modules/*": ["./Modules/*/resources/js/*"],
      "@shared/*": ["./resources/js/components/*"]
    }
  },
  "include": [
    "resources/js/**/*",
    "Modules/*/resources/js/**/*"
  ]
}
```

In module components:
```typescript
// Modules/Blog/resources/js/pages/BlogIndex.tsx
import { Button } from '@shared/Button';  // Resolved from main app
import { BlogCard } from './components/BlogCard';
```

**Advantage:** Single source of truth for shared UI.
**Disadvantage:** Requires careful bundling to avoid duplication.

---

## 3. Build Output Strategy: Monolithic vs Per-Module

| Aspect | Single Bundle | Per-Module Bundles |
|--------|---------------|-------------------|
| **Build Time** | Faster (1x) | Slower (5x for 5 modules) |
| **Initial Load** | One HTTP request | Multiple requests (lazy loadable) |
| **Cache Busting** | All bundles invalidate | Only changed module |
| **Shared Code** | Automatic deduplication | Manual control via manualChunks |
| **Future Expansion** | Hard to isolate | Easy to lazy-load modules |
| **Development HMR** | Single hot file | Per-module hot files |

**Recommendation:** Single bundle for MVP, migrate to per-module as app scales.

---

## 4. TypeScript Configuration for Multi-Module

```json
{
  "compilerOptions": {
    "target": "ESNext",
    "module": "ESNext",
    "moduleResolution": "bundler",
    "jsx": "react-jsx",
    "strict": true,
    "skipLibCheck": true,
    "noEmit": true,
    "paths": {
      "@/*": ["./resources/js/*"],
      "@modules/*": ["./Modules/*/resources/js/*"]
    }
  },
  "include": [
    "resources/js/**/*.ts",
    "resources/js/**/*.tsx",
    "Modules/*/resources/js/**/*.ts",
    "Modules/*/resources/js/**/*.tsx"
  ]
}
```

---

## 5. HMR Configuration Across Modules

### Single Config (Automatic HMR)
```javascript
export default defineConfig({
  server: {
    middlewareMode: false,
    hmr: {
      host: 'localhost',
      port: 5173,
    },
  },
});
// HMR works for all files under project root
```

### Per-Module Config (Separate Hot Files)
```javascript
// Modules/Blog/vite.config.js
export default defineConfig({
  server: {
    middlewareMode: false,
    hmr: {
      host: 'localhost',
      port: 5173, // Same port
      protocol: 'ws',
    },
  },
  plugins: [
    laravel({
      hotFile: '../../public/blog.hot',
      // ...
    }),
  ],
});

// Blade view:
@vite(['Modules/Blog/resources/js/app.js'])
// Laravel reads public/blog.hot and connects HMR
```

---

## 6. Current Project Configuration Analysis

**Existing Setup:**
- Root: `resources/js/app.tsx` (single entry)
- Modules: `Modules/*/vite.config.js` (separate configs)
- Issue: Separate vite processes, manual concurrency needed

**Recommended Unified Approach:**

```javascript
// Root vite.config.js (UNIFIED)
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import { glob } from 'glob';

const moduleInputs = glob.sync('Modules/*/resources/js/app.{js,tsx}');

export default defineConfig({
  plugins: [
    laravel({
      input: [
        'resources/js/app.tsx',
        ...moduleInputs,
      ],
      refresh: true,
    }),
    react(),
  ],
  build: {
    rollupOptions: {
      output: {
        manualChunks(id) {
          if (id.includes('node_modules')) {
            return 'vendor';
          }
          if (id.includes('/Modules/')) {
            const match = id.match(/\/Modules\/(\w+)\//);
            return match ? `module-${match[1].toLowerCase()}` : 'modules';
          }
        },
      },
    },
  },
});
```

Benefits:
- ✅ Single build process (`npm run build`)
- ✅ Single HMR connection in dev
- ✅ Automatic vendor deduplication
- ✅ Per-module JS chunks in production
- ✅ TypeScript paths work for all modules

---

## 7. Trade-offs Summary

| Factor | Monolithic | Per-Module |
|--------|-----------|-----------|
| Complexity | Low | High |
| DX (dev speed) | Fast HMR | Slower (separate processes) |
| Deployment | Simple | Complex (multiple builds) |
| Scaling | Hits limits ~5MB+ | Scales well |
| Shared Code | Easy | Needs manualChunks config |

**For Current Project:** Use unified single-config approach with `manualChunks` for module isolation. Migrate to per-module later if bundle size > 2MB.

---

## Unresolved Questions

1. **How to handle separate Tailwind configs per module?** (Need module-specific utility classes)
2. **Should module entry points be pages or full apps?** (Affects routing strategy)
3. **How to version module assets independently?** (Cache busting for live updates)
