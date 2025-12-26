# Phase 01: Vite & TypeScript Configuration

**Date:** 2025-12-25
**Priority:** P0 (Critical Path)
**Status:** pending
**Estimated Effort:** 2-3 hours

---

## Overview

Configure Vite and TypeScript to support multi-module frontend architecture. This is foundational - all subsequent phases depend on this.

## Key Insights (from research)

- Vite's `import.meta.glob` accepts array of patterns for multi-directory discovery
- `manualChunks` enables vendor code sharing across modules
- TypeScript `paths` and `include` must cover module directories
- Single unified config preferred over per-module configs

## Requirements

- [ ] Update `vite.config.js` with module path support
- [ ] Configure `manualChunks` for shared vendor bundles
- [ ] Extend `tsconfig.json` paths and includes
- [ ] Verify HMR works for module directories
- [ ] Test production build generates proper chunks

## Related Code Files

```
/Users/binjuhor/Development/shadcn-admin/vite.config.js
/Users/binjuhor/Development/shadcn-admin/tsconfig.json
/Users/binjuhor/Development/shadcn-admin/resources/js/app.tsx
/Users/binjuhor/Development/shadcn-admin/package.json
```

## Implementation Steps

### 1. Update vite.config.js

Current config is minimal (single entry). Update to:

```javascript
import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import path from 'path';

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '');

  return {
    plugins: [
      laravel({
        input: 'resources/js/app.tsx',
        refresh: [
          'resources/views/**',
          'Modules/*/resources/views/**',
          'Modules/*/resources/js/**',
        ],
      }),
      react(),
    ],
    resolve: {
      alias: {
        '@': path.resolve(__dirname, 'resources/js'),
        '@modules': path.resolve(__dirname, 'Modules'),
      },
    },
    build: {
      rollupOptions: {
        output: {
          manualChunks(id) {
            // Share vendor libs across all modules
            if (id.includes('node_modules')) {
              if (id.includes('react') || id.includes('react-dom')) {
                return 'vendor-react';
              }
              if (id.includes('@radix-ui') || id.includes('lucide-react')) {
                return 'vendor-ui';
              }
              return 'vendor';
            }
            // Separate module chunks
            if (id.includes('/Modules/')) {
              const match = id.match(/\/Modules\/(\w+)\//);
              if (match) {
                return `module-${match[1].toLowerCase()}`;
              }
            }
          },
        },
      },
    },
  };
});
```

### 2. Update tsconfig.json

Extend paths and includes for modules:

```json
{
  "compilerOptions": {
    "allowJs": true,
    "module": "ESNext",
    "moduleResolution": "bundler",
    "jsx": "react-jsx",
    "strict": true,
    "isolatedModules": true,
    "target": "ESNext",
    "esModuleInterop": true,
    "forceConsistentCasingInFileNames": true,
    "skipLibCheck": true,
    "noEmit": true,
    "paths": {
      "@/*": ["./resources/js/*"],
      "@modules/*": ["./Modules/*/resources/js/*"],
      "ziggy-js": ["./vendor/tightenco/ziggy"]
    }
  },
  "include": [
    "resources/js/**/*.ts",
    "resources/js/**/*.tsx",
    "resources/js/**/*.d.ts",
    "Modules/*/resources/js/**/*.ts",
    "Modules/*/resources/js/**/*.tsx"
  ]
}
```

### 3. Verify Build Configuration

Run commands to validate:

```bash
# Development - verify HMR
pnpm run dev

# Production - verify chunks
pnpm run build
ls -la public/build/assets/
```

Expected output should show:
- `vendor-react-*.js`
- `vendor-ui-*.js`
- `vendor-*.js`
- `module-*.js` (after migration)

## Todo List

- [ ] Backup current `vite.config.js`
- [ ] Update Vite config with aliases and manualChunks
- [ ] Update `tsconfig.json` paths and includes
- [ ] Run `pnpm run dev` - verify no errors
- [ ] Run `pnpm run build` - verify chunk output
- [ ] Test HMR by editing a component

## Success Criteria

1. `pnpm run dev` starts without errors
2. `pnpm run build` completes successfully
3. Production build shows separate vendor chunks
4. TypeScript recognizes `@/*` and `@modules/*` aliases
5. HMR works for files in `Modules/*/resources/js/`

## Risk Assessment

| Risk | Impact | Mitigation |
|------|--------|------------|
| Build breaks existing pages | High | Test all current pages after config change |
| TypeScript path resolution fails | Medium | Verify IDE recognizes new paths |
| HMR stops working | Medium | Test hot reload before proceeding |

## Next Steps

After completion, proceed to Phase 02: Inertia Page Resolution
