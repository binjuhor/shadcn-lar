# Phase 03: Sidebar Integration

## Context Links

- Parent: [plan.md](./plan.md)
- Previous: [phase-02-collabs-component.md](./phase-02-collabs-component.md)
- Related: `resources/js/components/layout/app-sidebar.tsx`

## Overview

- **Priority:** High
- **Status:** Pending
- **Description:** Integrate collabs container into app sidebar

## Key Insights

- Current sidebar loops through all navGroups
- Need to separate General from others
- Permission filtering must apply to collabs groups too
- Minimal changes to existing structure

## Requirements

### Functional
- General group always visible at top
- Remaining groups wrapped in NavCollabsContainer
- Permission filtering works on all groups
- Add collabsConfig to sidebar data

### Non-functional
- No breaking changes to existing functionality
- Clean separation of concerns

## Architecture

### Updated app-sidebar.tsx flow:

```
SidebarContent
├── NavGroup (General) - always shown
└── NavCollabsContainer
    ├── NavGroup (Access Control)
    ├── NavGroup (Ecommerce)
    ├── NavGroup (Blog)
    ├── NavGroup (Pages)
    └── NavGroup (Other)
```

### Data structure update:

```typescript
// sidebar-data.ts
export const sidebarData: SidebarData = {
  // ... existing
  collabsConfig: {
    title: 'Modules',
    defaultOpen: false,
    storageKey: 'sidebar-collabs-open'
  }
}
```

### Sidebar logic:

```typescript
// app-sidebar.tsx
const generalGroup = filteredNavGroups.find(g => g.title === 'General')
const collabsGroups = filteredNavGroups.filter(g => g.title !== 'General')
```

## Related Code Files

**Modify:**
- `/Users/binjuhor/Development/shadcn-admin/resources/js/components/layout/app-sidebar.tsx`
- `/Users/binjuhor/Development/shadcn-admin/resources/js/components/layout/data/sidebar-data.ts`

**Reference:**
- `/Users/binjuhor/Development/shadcn-admin/resources/js/hooks/use-permission.ts`

## Implementation Steps

1. Update sidebar-data.ts
   - Add collabsConfig object
   - Keep navGroups structure unchanged

2. Update app-sidebar.tsx
   - Import NavCollabsContainer
   - Split filteredNavGroups into general vs collabs
   - Render General group directly
   - Render collabs groups in NavCollabsContainer
   - Pass collabsConfig from sidebarData

## Todo List

- [ ] Add collabsConfig to sidebar-data.ts
- [ ] Update app-sidebar.tsx imports
- [ ] Separate General from other groups
- [ ] Render NavCollabsContainer with collabs groups
- [ ] Test permission filtering still works
- [ ] Test both sidebar modes (expanded/icon)

## Success Criteria

- General group visible at top
- Other groups in collapsible "Modules" section
- Toggle persists across refresh
- Permission filtering works correctly
- No visual regression on existing functionality

## Risk Assessment

| Risk | Impact | Mitigation |
|------|--------|------------|
| General group missing | High | Fallback to show all if not found |
| Permission filtering broken | High | Test all permission scenarios |
| Empty collabs container | Low | Don't render if no groups after filtering |

## Security Considerations

- Permission filtering unchanged - still server-controlled
- No new permission vectors introduced

## Next Steps

After implementation:
- [ ] Test all nav group functionality
- [ ] Test responsive behavior
- [ ] Run linting
