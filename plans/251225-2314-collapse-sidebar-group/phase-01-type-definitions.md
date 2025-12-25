# Phase 01: Type Definitions

## Context Links

- Parent: [plan.md](./plan.md)
- Related: `resources/js/components/layout/types.ts`

## Overview

- **Priority:** High
- **Status:** Pending
- **Description:** Update type definitions to support collabs grouping

## Key Insights

- Current `SidebarData` has flat `navGroups` array
- Need marker to identify which groups go in collabs container
- Keep backward compatible - existing structure works

## Requirements

### Functional
- Add optional `collabs` flag to `NavGroup` type
- Add `CollabsConfig` type for container settings

### Non-functional
- Type-safe
- Minimal changes to existing types

## Architecture

```typescript
// New type for collabs config
interface CollabsConfig {
  title: string           // "Collabs" or custom
  defaultOpen?: boolean   // default: false
  storageKey?: string     // localStorage key
}

// Extend SidebarData
interface SidebarData {
  user: User
  teams: Team[]
  navGroups: NavGroup[]
  collabsConfig?: CollabsConfig  // optional config
}
```

## Related Code Files

**Modify:**
- `/Users/binjuhor/Development/shadcn-admin/resources/js/components/layout/types.ts`

## Implementation Steps

1. Add `CollabsConfig` interface to types.ts
2. Add optional `collabsConfig` to `SidebarData` interface
3. Add optional `isCollabsGroup?: boolean` to `NavGroup` interface

## Todo List

- [ ] Add CollabsConfig interface
- [ ] Update SidebarData interface
- [ ] Update NavGroup interface
- [ ] Verify TypeScript compilation

## Success Criteria

- Types compile without errors
- Existing code unaffected (backward compatible)
- IntelliSense works for new properties

## Risk Assessment

| Risk | Impact | Mitigation |
|------|--------|------------|
| Breaking existing code | High | Make all new fields optional |

## Security Considerations

None - types only.

## Next Steps

Proceed to Phase 02: Collabs Component
