# Phase 3: Integration & Polish

**Parent:** [plan.md](./plan.md)
**Dependencies:** [Phase 1](./phase-01-backend-api.md), [Phase 2](./phase-02-frontend-ui.md)

## Overview

| Field | Value |
|-------|-------|
| Date | 2025-12-25 |
| Description | Add warning dialog, access control, loading states, tests |
| Priority | Medium |
| Status | Pending |
| Estimated | 2-3 hours |

## Key Insights

- Warning dialog before disabling helps prevent accidental disruption
- Loading states improve UX during API calls
- Access control must work at both nav and page level
- End-to-end testing ensures complete flow works

## Requirements

1. Warning confirmation dialog before disabling any module
2. Loading states during toggle operations
3. Hide Modules nav item from non-Super Admin users
4. End-to-end testing for complete flow
5. Unit tests for controller

## Architecture

### Warning Dialog Flow

```
User clicks disable switch
        ↓
Show AlertDialog: "Are you sure?"
        ↓
    [Cancel] → Close dialog, no action
    [Confirm] → Proceed with toggle API call
```

## Related Code Files

| File | Action | Purpose |
|------|--------|---------|
| `resources/js/pages/settings/modules/modules-form.tsx` | Modify | Add dialog, loading |
| `resources/js/pages/settings/data/nav-items.tsx` | Modify | Add permission check |
| `resources/js/layouts/settings-layout/index.tsx` | Modify | Filter nav by permission |
| `tests/Feature/ModulesControllerTest.php` | Create | Backend tests |

## Implementation Steps

### Step 1: Add Warning Dialog to Modules Form

**File:** `resources/js/pages/settings/modules/modules-form.tsx` (update)

```tsx
import { useState } from 'react'
import { router } from '@inertiajs/react'
import { toast } from '@/hooks/use-toast'
import { Switch } from '@/components/ui/switch'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { IconLoader2 } from '@tabler/icons-react'
import { type Module } from '@/types'

interface Props {
  modules: Module[]
}

export function ModulesForm({ modules: initialModules }: Props) {
  const [modules, setModules] = useState<Module[]>(initialModules)
  const [processing, setProcessing] = useState<string | null>(null)
  const [pendingDisable, setPendingDisable] = useState<Module | null>(null)

  function handleToggle(module: Module) {
    if (module.isCore) {
      toast({
        title: 'Cannot disable core module',
        description: `${module.name} is required for system operation.`,
        variant: 'destructive',
      })
      return
    }

    // Show warning when disabling
    if (module.enabled) {
      setPendingDisable(module)
      return
    }

    executeToggle(module)
  }

  function executeToggle(module: Module) {
    const previousState = [...modules]

    setModules(prev =>
      prev.map(m =>
        m.name === module.name ? { ...m, enabled: !m.enabled } : m
      )
    )
    setProcessing(module.name)
    setPendingDisable(null)

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
    <>
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
            <div className='flex items-center gap-2'>
              {processing === module.name && (
                <IconLoader2 className='h-4 w-4 animate-spin text-muted-foreground' />
              )}
              <Switch
                checked={module.enabled}
                onCheckedChange={() => handleToggle(module)}
                disabled={module.isCore || processing === module.name}
                aria-readonly={module.isCore}
              />
            </div>
          </div>
        ))}
      </div>

      <AlertDialog
        open={!!pendingDisable}
        onOpenChange={(open) => !open && setPendingDisable(null)}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Disable {pendingDisable?.name}?</AlertDialogTitle>
            <AlertDialogDescription>
              Disabling this module will remove its functionality from the system.
              Other features or modules may depend on it. Are you sure you want to continue?
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction
              onClick={() => pendingDisable && executeToggle(pendingDisable)}
              className='bg-destructive text-destructive-foreground hover:bg-destructive/90'
            >
              Disable Module
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  )
}
```

### Step 2: Add Permission Check to Nav Items

**File:** `resources/js/pages/settings/data/nav-items.tsx` (update structure)

```tsx
import {
  IconBrowserCheck,
  IconNotification,
  IconPalette,
  IconPackages,
  IconTool,
  IconUser,
} from '@tabler/icons-react'

interface SettingsNavItem {
  title: string
  icon: React.ReactNode
  href: string
  superAdminOnly?: boolean
}

export const settingsNavItems: SettingsNavItem[] = [
  {
    title: 'Profile',
    icon: <IconUser size={18} />,
    href: '/dashboard/settings',
  },
  {
    title: 'Account',
    icon: <IconTool size={18} />,
    href: '/dashboard/settings/account',
  },
  {
    title: 'Appearance',
    icon: <IconPalette size={18} />,
    href: '/dashboard/settings/appearance',
  },
  {
    title: 'Notifications',
    icon: <IconNotification size={18} />,
    href: '/dashboard/settings/notifications',
  },
  {
    title: 'Display',
    icon: <IconBrowserCheck size={18} />,
    href: '/dashboard/settings/display',
  },
  {
    title: 'Modules',
    icon: <IconPackages size={18} />,
    href: '/dashboard/settings/modules',
    superAdminOnly: true,
  },
]
```

### Step 3: Filter Nav in Settings Layout

**File:** `resources/js/layouts/settings-layout/index.tsx` (add filtering)

Add import:
```tsx
import { usePermission } from '@/hooks/use-permission'
```

Inside component, before return:
```tsx
const { isSuperAdmin } = usePermission()

const filteredNavItems = settingsNavItems.filter(
  item => !item.superAdminOnly || isSuperAdmin()
)
```

Update SidebarNav usage:
```tsx
<SidebarNav items={filteredNavItems} />
```

### Step 4: Create Backend Tests

**File:** `tests/Feature/ModulesControllerTest.php`

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nwidart\Modules\Facades\Module;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ModulesControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'Super Admin', 'guard_name' => 'web']);
    }

    public function test_super_admin_can_view_modules_page(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        $response = $this->actingAs($user)->get('/dashboard/settings/modules');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('settings/modules/index')
            ->has('modules')
        );
    }

    public function test_non_super_admin_cannot_view_modules_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard/settings/modules');

        $response->assertStatus(403);
    }

    public function test_super_admin_can_toggle_module(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        $response = $this->actingAs($user)->patch('/dashboard/settings/modules/toggle', [
            'name' => 'Blog',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    public function test_cannot_disable_permission_module(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        $response = $this->actingAs($user)->patch('/dashboard/settings/modules/toggle', [
            'name' => 'Permission',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_toggle_non_existent_module_returns_404(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        $response = $this->actingAs($user)->patch('/dashboard/settings/modules/toggle', [
            'name' => 'NonExistentModule',
        ]);

        $response->assertStatus(404);
    }
}
```

### Step 5: Manual E2E Testing Checklist

1. Login as Super Admin
2. Navigate to Settings > Modules
3. Verify all modules listed with correct metadata
4. Toggle a non-core module (e.g., Blog) off
5. Confirm dialog appears
6. Click Cancel - verify no change
7. Click Disable - verify switch toggles, toast appears
8. Refresh page - verify state persisted
9. Toggle module back on (no dialog)
10. Try to toggle Permission - verify disabled
11. Logout, login as regular user
12. Verify Modules nav item not visible
13. Direct URL `/dashboard/settings/modules` returns 403

## Todo List

- [ ] Update modules-form.tsx with AlertDialog
- [ ] Add superAdminOnly flag to nav-items.tsx
- [ ] Filter nav items in settings-layout/index.tsx
- [ ] Create ModulesControllerTest.php
- [ ] Run backend tests: `php artisan test --filter=ModulesControllerTest`
- [ ] Manual E2E testing
- [ ] Fix any issues found

## Success Criteria

- [ ] Warning dialog shows before disabling
- [ ] Loading spinner appears during API call
- [ ] Modules nav hidden for non-Super Admin
- [ ] All backend tests pass
- [ ] E2E checklist completed

## Risk Assessment

| Risk | Probability | Impact | Mitigation |
|------|-------------|--------|------------|
| Dialog dismissed accidentally | Low | Low | Require explicit Cancel/Confirm |
| Nav filter breaks layout | Very Low | Medium | Filter returns array, not null |
| Tests flaky with Module facade | Low | Medium | Use RefreshDatabase trait |

## Security Considerations

- Defense in depth: Client nav filter + backend 403
- Dialog prevents accidental disable
- No new attack surface introduced

## Next Steps

After all phases complete:
1. Code review via `code-reviewer` agent
2. Merge to feature branch
3. Deploy to staging for QA

---

## Component Reference

### AlertDialog (from Shadcn)

```tsx
<AlertDialog>
  <AlertDialogTrigger>Open</AlertDialogTrigger>
  <AlertDialogContent>
    <AlertDialogHeader>
      <AlertDialogTitle>Are you sure?</AlertDialogTitle>
      <AlertDialogDescription>
        This action cannot be undone.
      </AlertDialogDescription>
    </AlertDialogHeader>
    <AlertDialogFooter>
      <AlertDialogCancel>Cancel</AlertDialogCancel>
      <AlertDialogAction>Continue</AlertDialogAction>
    </AlertDialogFooter>
  </AlertDialogContent>
</AlertDialog>
```

### Loading Spinner

```tsx
import { IconLoader2 } from '@tabler/icons-react'

<IconLoader2 className='h-4 w-4 animate-spin' />
```
