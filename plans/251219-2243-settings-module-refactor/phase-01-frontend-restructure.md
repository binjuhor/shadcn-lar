# Phase 01: Frontend Restructure

## Context Links
- Parent: [plan.md](./plan.md)
- Reference: `resources/js/pages/users/` (target pattern)
- Reference: `resources/js/pages/tasks/` (target pattern)

## Overview

| Field | Value |
|-------|-------|
| Date | 2024-12-19 |
| Priority | High |
| Implementation Status | Pending |
| Review Status | Pending |

**Description:** Create context provider, centralize data schemas, extract nav config, restructure settings sub-pages to use provider pattern.

## Key Insights

1. `users` and `tasks` modules use Context Provider pattern for dialog state management
2. Both use `useDialogState` hook from `@/hooks/use-dialog-state`
3. Zod schemas centralized in `data/schema.ts`
4. Settings currently has inline schemas per form component
5. `SettingLayout` has nav items hardcoded - should be extracted

## Requirements

### Functional
- Context provider for settings state management
- Centralized Zod schemas for all settings forms
- Nav items extracted to config file
- Sub-pages wrapped with provider

### Non-Functional
- Files under 200 lines
- TypeScript strict mode compliance
- Follow existing naming conventions

## Architecture

```
resources/js/pages/settings/
├── context/
│   └── settings-context.tsx      # NEW - State management
├── data/
│   ├── schema.ts                 # NEW - Zod schemas
│   └── nav-items.tsx             # NEW - Sidebar nav config
├── components/
│   ├── content-section.tsx       # KEEP - No changes
│   └── sidebar-nav.tsx           # KEEP - No changes
├── profile/
│   ├── index.tsx                 # MODIFY - Use context
│   └── profile-form.tsx          # MODIFY - Use centralized schema
├── account/
│   ├── index.tsx                 # MODIFY - Use context
│   └── account-form.tsx          # MODIFY - Use centralized schema
├── appearance/
│   ├── index.tsx                 # MODIFY - Use context
│   └── appearance-form.tsx       # MODIFY - Use centralized schema
├── notifications/
│   ├── index.tsx                 # MODIFY - Use context
│   └── notifications-form.tsx    # MODIFY - Use centralized schema
├── display/
│   ├── index.tsx                 # MODIFY - Use context
│   └── display-form.tsx          # MODIFY - Use centralized schema
└── index.tsx                     # DELETE - Redundant
```

## Related Code Files

### CREATE
| File | Description |
|------|-------------|
| `resources/js/pages/settings/context/settings-context.tsx` | Context provider with dialog state |
| `resources/js/pages/settings/data/schema.ts` | Zod schemas for all settings types |
| `resources/js/pages/settings/data/nav-items.tsx` | Sidebar nav items config |

### MODIFY
| File | Changes |
|------|---------|
| `resources/js/layouts/settings-layout/index.tsx` | Import nav items from config |
| `resources/js/pages/settings/profile/index.tsx` | Wrap with SettingsProvider |
| `resources/js/pages/settings/account/index.tsx` | Wrap with SettingsProvider |
| `resources/js/pages/settings/appearance/index.tsx` | Wrap with SettingsProvider |
| `resources/js/pages/settings/notifications/index.tsx` | Wrap with SettingsProvider |
| `resources/js/pages/settings/display/index.tsx` | Wrap with SettingsProvider |

### DELETE
| File | Reason |
|------|--------|
| `resources/js/pages/settings/index.tsx` | Redundant, layout handles settings main page |

## Implementation Steps

### Step 1: Create Context Provider
Create `resources/js/pages/settings/context/settings-context.tsx`:

```tsx
import React, { useState } from 'react'
import useDialogState from '@/hooks/use-dialog-state'

type SettingsTab = 'profile' | 'account' | 'appearance' | 'notifications' | 'display'
type SettingsDialogType = 'save-confirm' | 'discard-confirm' | null

interface SettingsContextType {
  activeTab: SettingsTab
  setActiveTab: (tab: SettingsTab) => void
  isDirty: boolean
  setIsDirty: (dirty: boolean) => void
  open: SettingsDialogType
  setOpen: (str: SettingsDialogType) => void
}

const SettingsContext = React.createContext<SettingsContextType | null>(null)

interface Props {
  children: React.ReactNode
  defaultTab?: SettingsTab
}

export default function SettingsProvider({ children, defaultTab = 'profile' }: Props) {
  const [open, setOpen] = useDialogState<SettingsDialogType>(null)
  const [activeTab, setActiveTab] = useState<SettingsTab>(defaultTab)
  const [isDirty, setIsDirty] = useState(false)

  return (
    <SettingsContext.Provider value={{
      activeTab, setActiveTab,
      isDirty, setIsDirty,
      open, setOpen
    }}>
      {children}
    </SettingsContext.Provider>
  )
}

export const useSettings = () => {
  const context = React.useContext(SettingsContext)
  if (!context) {
    throw new Error('useSettings must be used within <SettingsProvider>')
  }
  return context
}
```

### Step 2: Create Centralized Schemas
Create `resources/js/pages/settings/data/schema.ts`:

```ts
import { z } from 'zod'

// Profile Schema
export const profileFormSchema = z.object({
  username: z.string().min(2).max(30),
  email: z.string().email(),
  bio: z.string().max(160).optional(),
  urls: z.array(z.object({ value: z.string().url() })).optional(),
})
export type ProfileFormValues = z.infer<typeof profileFormSchema>

// Account Schema
export const accountFormSchema = z.object({
  name: z.string().min(2).max(30),
  dob: z.date(),
  language: z.string(),
})
export type AccountFormValues = z.infer<typeof accountFormSchema>

// Appearance Schema
export const appearanceFormSchema = z.object({
  theme: z.enum(['light', 'dark']),
  font: z.enum(['inter', 'manrope', 'system']),
})
export type AppearanceFormValues = z.infer<typeof appearanceFormSchema>

// Notifications Schema
export const notificationsFormSchema = z.object({
  type: z.enum(['all', 'mentions', 'none']),
  mobile: z.boolean(),
  communication_emails: z.boolean(),
  social_emails: z.boolean(),
  marketing_emails: z.boolean(),
  security_emails: z.boolean(),
})
export type NotificationsFormValues = z.infer<typeof notificationsFormSchema>

// Display Schema
export const displayFormSchema = z.object({
  items: z.array(z.string()).refine((value) => value.length > 0),
})
export type DisplayFormValues = z.infer<typeof displayFormSchema>
```

### Step 3: Extract Nav Items Config
Create `resources/js/pages/settings/data/nav-items.tsx`:

```tsx
import {
  IconBrowserCheck,
  IconNotification,
  IconPalette,
  IconTool,
  IconUser,
} from '@tabler/icons-react'

export const settingsNavItems = [
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
]
```

### Step 4: Update SettingLayout
Modify `resources/js/layouts/settings-layout/index.tsx` to import nav items from config:

```tsx
// Replace hardcoded sidebarNavItems with:
import { settingsNavItems } from '@/pages/settings/data/nav-items'

// Then use settingsNavItems in the component
```

### Step 5: Update Sub-page Index Files
Each sub-page index should be wrapped with SettingsProvider. Example for `profile/index.tsx`:

```tsx
import { SettingLayout } from "@/layouts"
import SettingsProvider from '../context/settings-context'
import ContentSection from '../components/content-section'
import ProfileForm from './profile-form'

export default function SettingsProfile() {
  return (
    <SettingsProvider defaultTab="profile">
      <SettingLayout title="User profile">
        <ContentSection
          title='Profile'
          desc='This is how others will see you on the site.'
        >
          <ProfileForm />
        </ContentSection>
      </SettingLayout>
    </SettingsProvider>
  )
}
```

### Step 6: Delete Redundant Index
Delete `resources/js/pages/settings/index.tsx` as it duplicates SettingLayout functionality.

## Todo List

- [ ] Create `settings-context.tsx` with dialog state
- [ ] Create `schema.ts` with all Zod schemas
- [ ] Create `nav-items.tsx` with sidebar config
- [ ] Update `settings-layout/index.tsx` to use nav-items config
- [ ] Update `profile/index.tsx` to use provider
- [ ] Update `account/index.tsx` to use provider
- [ ] Update `appearance/index.tsx` to use provider
- [ ] Update `notifications/index.tsx` to use provider
- [ ] Update `display/index.tsx` to use provider
- [ ] Delete redundant `settings/index.tsx`
- [ ] Verify TypeScript compilation
- [ ] Test all settings pages render correctly

## Success Criteria

1. All settings sub-pages use SettingsProvider
2. Zod schemas centralized in single file
3. Nav items extracted from layout
4. No TypeScript errors
5. All pages render and function correctly

## Risk Assessment

| Risk | Impact | Mitigation |
|------|--------|------------|
| Breaking nav routing | High | Test each nav link after changes |
| Form validation changes | Medium | Keep schema logic identical, only move location |
| Import path issues | Low | Use alias `@/pages/settings/...` consistently |

## Security Considerations

- No sensitive data exposed in context
- Form validation remains intact
- No new attack vectors introduced

## Next Steps

After Phase 1 completion:
1. Proceed to Phase 2: Backend Integration
2. Create SettingsController.php
3. Connect forms to API endpoints
