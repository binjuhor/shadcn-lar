# Phase 02: Collabs Component & Hook

## Context Links

- Parent: [plan.md](./plan.md)
- Previous: [phase-01-type-definitions.md](./phase-01-type-definitions.md)
- Related: `resources/js/components/layout/nav-group.tsx`

## Overview

- **Priority:** High
- **Status:** Pending
- **Description:** Create collabs container component and localStorage hook

## Key Insights

- Reuse existing `Collapsible` from Radix
- Follow `nav-group.tsx` patterns
- Handle sidebar collapsed (icon) state
- Need custom hook for localStorage persistence

## Requirements

### Functional
- Collapsible container wrapping multiple NavGroups
- Toggle button with "Modules" label
- Persist open/closed state in localStorage
- Show chevron indicator
- Work in both expanded and icon sidebar modes

### Non-functional
- Smooth animation (match existing collapsibles)
- Accessible (keyboard nav, ARIA)
- Under 200 lines total

## Architecture

### Hook: useCollabsState

```typescript
// resources/js/hooks/use-collabs-state.ts
function useCollabsState(key: string, defaultOpen: boolean) {
  const [isOpen, setIsOpen] = useState(() => {
    const stored = localStorage.getItem(key)
    return stored !== null ? stored === 'true' : defaultOpen
  })

  useEffect(() => {
    localStorage.setItem(key, String(isOpen))
  }, [key, isOpen])

  return [isOpen, setIsOpen] as const
}
```

### Component: NavCollabsContainer

```typescript
// resources/js/components/layout/nav-collabs-container.tsx
interface NavCollabsContainerProps {
  title: string
  groups: NavGroup[]
  defaultOpen?: boolean
  storageKey?: string
}
```

UI structure:
```
SidebarGroup
├── Header with toggle button
│   ├── IconApps icon
│   ├── "Modules" label
│   └── ChevronDown (rotates)
└── CollapsibleContent
    └── NavGroup[] (rendered normally)
```

## Related Code Files

**Create:**
- `/Users/binjuhor/Development/shadcn-admin/resources/js/hooks/use-collabs-state.ts`
- `/Users/binjuhor/Development/shadcn-admin/resources/js/components/layout/nav-collabs-container.tsx`

**Reference:**
- `/Users/binjuhor/Development/shadcn-admin/resources/js/components/layout/nav-group.tsx`

## Implementation Steps

1. Create `use-collabs-state.ts` hook
   - useState with lazy init from localStorage
   - useEffect to persist changes
   - Return tuple [isOpen, setIsOpen]

2. Create `nav-collabs-container.tsx` component
   - Import Collapsible, SidebarGroup, NavGroup
   - Props: title, groups, defaultOpen, storageKey
   - Use useCollabsState hook
   - Render header with toggle trigger
   - Map groups to NavGroup components in content

3. Handle icon sidebar mode
   - Check `useSidebar().state === 'collapsed'`
   - Show dropdown menu instead of collapsible
   - Similar pattern to `SidebarMenuCollapsedDropdown`

## Todo List

- [ ] Create use-collabs-state.ts hook
- [ ] Create nav-collabs-container.tsx component
- [ ] Implement expanded sidebar mode
- [ ] Implement collapsed (icon) sidebar mode
- [ ] Add proper ARIA attributes
- [ ] Test localStorage persistence

## Success Criteria

- Container expands/collapses on click
- State persists across page reload
- Works in icon mode sidebar
- Smooth animation matches existing collapsibles
- Keyboard accessible (Enter/Space to toggle)

## Risk Assessment

| Risk | Impact | Mitigation |
|------|--------|------------|
| localStorage not available | Low | Default to open, wrap in try-catch |
| Icon mode complexity | Medium | Reuse dropdown pattern from nav-group |

## Security Considerations

- localStorage: only stores boolean preference, no sensitive data

## Next Steps

Proceed to Phase 03: Sidebar Integration
