# Phase 2: Frontend Templates

**Parent:** [plan.md](./plan.md)
**Dependencies:** Phase 1 complete

## Overview

| Field | Value |
|-------|-------|
| Date | 2024-12-26 |
| Description | Create React/TypeScript stub templates |
| Priority | High |
| Implementation | Pending |
| Review | Pending |

## Key Insights

- Inertia uses `Module::page/path` syntax (handled in app.tsx)
- Import alias: `@modules/ModuleName/resources/js/types/`
- AuthenticatedLayout from `@/layouts`
- Shadcn components from `@/components/ui/`

## Requirements

1. Index page template with AuthenticatedLayout
2. TypeScript types template with base interface
3. Follow Finance module patterns

## Architecture

### Page Structure Pattern
```tsx
import { AuthenticatedLayout } from '@/layouts'
import { Main } from '@/components/layout/main'

export default function Index() {
  return (
    <AuthenticatedLayout title="{{moduleName}}">
      <Main>
        {/* Content */}
      </Main>
    </AuthenticatedLayout>
  )
}
```

### Type Import Pattern
```tsx
import type { Entity } from '@modules/{{moduleName}}/resources/js/types/{{moduleNameLower}}'
```

## Related Code Files

- `stubs/module-scaffold/resources/js/pages/index.stub` (create)
- `stubs/module-scaffold/resources/js/types/module.stub` (create)
- `Modules/Finance/resources/js/pages/index.tsx` (reference)
- `Modules/Finance/resources/js/types/finance.ts` (reference)

## Implementation Steps

### Step 1: Index Page Stub

**stubs/module-scaffold/resources/js/pages/index.stub:**
```tsx
import { Link } from '@inertiajs/react'
import { AuthenticatedLayout } from '@/layouts'
import { Main } from '@/components/layout/main'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Plus } from 'lucide-react'

interface Props {
  // Add props as needed
}

export default function {{moduleName}}Index({}: Props) {
  return (
    <AuthenticatedLayout title="{{moduleName}}">
      <Main>
        <div className="mb-4 flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold tracking-tight">{{moduleName}}</h1>
            <p className="text-muted-foreground">
              Manage your {{moduleNameLower}} data
            </p>
          </div>
        </div>

        <Card>
          <CardHeader>
            <CardTitle>Welcome to {{moduleName}}</CardTitle>
            <CardDescription>
              This module is ready for development
            </CardDescription>
          </CardHeader>
          <CardContent>
            <p className="text-muted-foreground">
              Start building your {{moduleNameLower}} features here.
            </p>
          </CardContent>
        </Card>
      </Main>
    </AuthenticatedLayout>
  )
}
```

### Step 2: Types Stub

**stubs/module-scaffold/resources/js/types/module.stub:**
```tsx
/**
 * {{moduleName}} Module Types
 */

export interface Base{{moduleName}}Entity {
  id: number
  created_at: string
  updated_at: string
}

// Add module-specific interfaces below

export interface PaginatedData<T> {
  data: T[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}
```

### Step 3: Vite Config Stub

**stubs/module-scaffold/vite.config.stub:**
```js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    build: {
        outDir: '../../public/build-{{moduleNameLower}}',
        emptyOutDir: true,
        manifest: true,
    },
    plugins: [
        laravel({
            publicDirectory: '../../public',
            buildDirectory: 'build-{{moduleNameLower}}',
            input: [
                __dirname + '/resources/assets/sass/app.scss',
                __dirname + '/resources/assets/js/app.js'
            ],
            refresh: true,
        }),
    ],
});
```

### Step 4: Package.json Stub

**stubs/module-scaffold/package.json.stub:**
```json
{
    "private": true,
    "type": "module",
    "scripts": {
        "dev": "vite",
        "build": "vite build"
    },
    "devDependencies": {
        "laravel-vite-plugin": "^1.2.0",
        "vite": "^6.0.0"
    }
}
```

## Todo List

- [ ] Create index.stub page template
- [ ] Create module.stub types template
- [ ] Create vite.config.stub
- [ ] Create package.json.stub
- [ ] Update command to generate frontend files
- [ ] Test Inertia page resolution

## Success Criteria

- [ ] Generated index.tsx follows project patterns
- [ ] TypeScript types file created with base interface
- [ ] Import from `@modules/` works correctly
- [ ] Page renders via `Inertia::render('ModuleName::index')`

## Risk Assessment

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| Import path mismatch | Medium | High | Test with actual module |
| Layout changes break template | Low | Medium | Use minimal dependencies |

## Security Considerations

- No security concerns for static templates

## Next Steps

After Phase 2:
- Proceed to Phase 3 for CRUD generator
- Test full page rendering cycle
