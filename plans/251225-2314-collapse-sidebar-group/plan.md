# Collapse Sidebar Group Implementation Plan

## Overview

Each sidebar nav group (except General) has individual collapse/expand toggle. Clicking the group title collapses/expands its items. State persisted in localStorage.

## Implementation Status: ✅ COMPLETED

## Changes Made

### Modified Files:
- `resources/js/components/layout/types.ts` - Added `collapsible?: boolean` to NavGroup
- `resources/js/components/layout/nav-group.tsx` - Added collapsible behavior with Radix Collapsible
- `resources/js/components/layout/app-sidebar.tsx` - Integrated useCollapsibleGroups hook
- `resources/js/components/layout/data/sidebar-data.ts` - Marked groups as collapsible

### Created Files:
- `resources/js/hooks/use-collapsible-groups.ts` - State management with localStorage persistence

## Features

- ✅ Each group title clickable to collapse/expand
- ✅ Chevron icon rotates to indicate state
- ✅ State persisted in localStorage
- ✅ General group remains non-collapsible
- ✅ Works with permission filtering
- ✅ TypeScript types updated

## Dependencies

- Radix Collapsible (existing)
- localStorage API
- Lucide Icons (existing)
