# Phase 2: Frontend UI

**Parent:** [plan.md](./plan.md)
**Dependencies:** [Phase 1: Backend API](./phase-01-backend-api.md)

## Overview

| Field | Value |
|-------|-------|
| Date | 2025-12-25 |
| Description | Create modules management UI following existing settings patterns |
| Priority | High |
| Status | Pending |
| Estimated | 3-4 hours |

## Key Insights

- Follow `notifications-form.tsx` pattern: Switch components in bordered cards
- Use `SettingLayout` + `ContentSection` structure
- Forms use `react-hook-form` + `zod` (not needed here - just switches)
- API calls via `router.patch()` with `preserveScroll`, `onSuccess`, `onError`
- Existing `isSuperAdmin()` hook available in `use-permission.ts`

## Requirements

1. Page at `resources/js/pages/settings/modules/index.tsx`
2. List all modules with Switch toggles in bordered cards
3. Display metadata: name, description, status badge, priority
4. Add "Modules" nav item to settings sidebar (Super Admin only)
5. Optimistic UI updates with rollback on error
6. Toast notifications for success/error

## Architecture

### Component Structure

```
settings/modules/
├── index.tsx       # Page wrapper (SettingLayout + ContentSection)
└── modules-form.tsx # Module list with Switch cards
```

### Data Flow

```
Props (modules[]) → ModulesForm → Switch onChange → router.patch() → Toast
                                      ↓
                              Optimistic update
                                      ↓
                              onError: rollback
```

## Related Code Files

| File | Action | Purpose |
|------|--------|---------|
| `resources/js/pages/settings/modules/index.tsx` | Create | Page component |
| `resources/js/pages/settings/modules/modules-form.tsx` | Create | Module list with toggles |
| `resources/js/pages/settings/data/nav-items.tsx` | Modify | Add Modules nav item |
| `resources/js/pages/settings/context/settings-context.tsx` | Modify | Add 'modules' tab type |
| `resources/js/types/index.d.ts` | Modify | Add Module interface |

## Implementation Steps

### Step 1: Add Module Interface

**File:** `resources/js/types/index.d.ts` (append)

```typescript
export interface Module {
    name: string;
    alias: string;
    description: string;
    keywords: string[];
    priority: number;
    enabled: boolean;
    isCore: boolean;
}
```

### Step 2: Update Settings Context

**File:** `resources/js/pages/settings/context/settings-context.tsx`

Change line 4:
```typescript
type SettingsTab = 'profile' | 'account' | 'appearance' | 'notifications' | 'display' | 'modules'
```

### Step 3: Add Nav Item

**File:** `resources/js/pages/settings/data/nav-items.tsx`

Add import:
```typescript
import { IconPackages } from '@tabler/icons-react'
```

Add to `settingsNavItems` array (at end):
```typescript
{
  title: 'Modules',
  icon: <IconPackages size={18} />,
  href: '/dashboard/settings/modules',
},
```

### Step 4: Create Page Component

**File:** `resources/js/pages/settings/modules/index.tsx`

```tsx
import { SettingLayout } from '@/layouts'
import SettingsProvider from '../context/settings-context'
import ContentSection from '../components/content-section'
import { ModulesForm } from './modules-form'
import { type Module } from '@/types'

interface Props {
  modules: Module[]
}

export default function SettingsModules({ modules }: Props) {
  return (
    <SettingsProvider defaultTab='modules'>
      <SettingLayout title='Modules Settings'>
        <ContentSection
          title='Modules'
          desc='Enable or disable system modules. Some modules are required and cannot be disabled.'
        >
          <ModulesForm modules={modules} />
        </ContentSection>
      </SettingLayout>
    </SettingsProvider>
  )
}
```

### Step 5: Create Modules Form

**File:** `resources/js/pages/settings/modules/modules-form.tsx`

```tsx
import { useState } from 'react'
import { router } from '@inertiajs/react'
import { toast } from '@/hooks/use-toast'
import { Switch } from '@/components/ui/switch'
import { Badge } from '@/components/ui/badge'
import { type Module } from '@/types'

interface Props {
  modules: Module[]
}

export function ModulesForm({ modules: initialModules }: Props) {
  const [modules, setModules] = useState<Module[]>(initialModules)
  const [processing, setProcessing] = useState<string | null>(null)

  function handleToggle(module: Module) {
    if (module.isCore) {
      toast({
        title: 'Cannot disable core module',
        description: `${module.name} is required for system operation.`,
        variant: 'destructive',
      })
      return
    }

    const previousState = [...modules]

    setModules(prev =>
      prev.map(m =>
        m.name === module.name ? { ...m, enabled: !m.enabled } : m
      )
    )
    setProcessing(module.name)

    router.patch(
      '/dashboard/settings/modules/toggle',
      { name: module.name },
      {
        preserveScroll: true,
        onSuccess: () => {
          const action = module.enabled ? 'disabled' : 'enabled'
          toast({
            title: `Module ${action}`,
            description: `${module.name} has been ${action} successfully.`,
          })
          setProcessing(null)
        },
        onError: (errors) => {
          setModules(previousState)
          toast({
            title: 'Error toggling module',
            description: Object.values(errors).flat().join(', '),
            variant: 'destructive',
          })
          setProcessing(null)
        },
      }
    )
  }

  return (
    <div className='space-y-4'>
      {modules.map((module) => (
        <div
          key={module.name}
          className='flex flex-row items-center justify-between rounded-lg border p-4'
        >
          <div className='space-y-0.5'>
            <div className='flex items-center gap-2'>
              <span className='text-base font-medium'>{module.name}</span>
              {module.isCore && (
                <Badge variant='secondary' className='text-xs'>
                  Core
                </Badge>
              )}
              <Badge
                variant={module.enabled ? 'default' : 'outline'}
                className='text-xs'
              >
                {module.enabled ? 'Enabled' : 'Disabled'}
              </Badge>
            </div>
            {module.description && (
              <p className='text-sm text-muted-foreground'>
                {module.description}
              </p>
            )}
            {module.keywords.length > 0 && (
              <div className='flex gap-1 pt-1'>
                {module.keywords.slice(0, 3).map((keyword) => (
                  <Badge key={keyword} variant='outline' className='text-xs'>
                    {keyword}
                  </Badge>
                ))}
              </div>
            )}
          </div>
          <Switch
            checked={module.enabled}
            onCheckedChange={() => handleToggle(module)}
            disabled={module.isCore || processing === module.name}
            aria-readonly={module.isCore}
          />
        </div>
      ))}
    </div>
  )
}
```

## Todo List

- [ ] Add `Module` interface to `resources/js/types/index.d.ts`
- [ ] Update `SettingsTab` type in settings-context.tsx
- [ ] Add Modules nav item to nav-items.tsx
- [ ] Create `resources/js/pages/settings/modules/index.tsx`
- [ ] Create `resources/js/pages/settings/modules/modules-form.tsx`
- [ ] Test page renders with module list
- [ ] Test optimistic updates work
- [ ] Test error rollback works
- [ ] Test core module toggle prevented

## Success Criteria

- [ ] Page accessible at `/dashboard/settings/modules`
- [ ] All modules displayed with correct metadata
- [ ] Switch toggles update UI immediately (optimistic)
- [ ] Toast shown on success/error
- [ ] Core module (Permission) switch disabled
- [ ] Error rolls back optimistic update

## Risk Assessment

| Risk | Probability | Impact | Mitigation |
|------|-------------|--------|------------|
| Type mismatch | Low | Medium | Match backend structure exactly |
| Optimistic update race | Low | Low | Disable switch during processing |
| Missing icon | Very Low | Low | IconPackages exists in Tabler |

## Security Considerations

- Nav item visibility: Controlled by backend (403 if not Super Admin)
- Client-side core check: Defense in depth (backend also checks)
- No sensitive data exposed in module metadata

## Next Steps

After completion, proceed to [Phase 3: Integration & Polish](./phase-03-integration-polish.md)

---

## UI Reference

### Card Layout (from notifications-form.tsx)

```tsx
<FormItem className='flex flex-row items-center justify-between rounded-lg border p-4'>
  <div className='space-y-0.5'>
    <FormLabel className='text-base'>Label</FormLabel>
    <FormDescription>Description text</FormDescription>
  </div>
  <FormControl>
    <Switch checked={value} onCheckedChange={onChange} />
  </FormControl>
</FormItem>
```

### Expected Visual Layout

```
┌────────────────────────────────────────────────────────────────┐
│ Blog                                  [Core] [Enabled]  [====]│
│ Blog management module                                         │
│ [posts] [articles] [cms]                                      │
├────────────────────────────────────────────────────────────────┤
│ Permission                            [Core] [Enabled]  [====]│
│ Role and permission management module              (disabled)  │
│ [roles] [permissions] [rbac]                                  │
├────────────────────────────────────────────────────────────────┤
│ Ecommerce                                   [Enabled]   [====]│
│                                                                │
│                                                                │
└────────────────────────────────────────────────────────────────┘
```
