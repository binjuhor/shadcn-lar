# Phase 03: Frontend User

**Status:** Pending
**Priority:** High

## Context

- [Main Plan](./plan.md)
- [Phase 01: Backend Core](./phase-01-backend-core.md)

## Overview

Implement frontend for user notification management.

## Related Files

**To Create:**
- `resources/js/pages/notifications/index.tsx` - List notifications
- `resources/js/pages/notifications/components/notification-item.tsx`
- `resources/js/pages/notifications/components/notification-list.tsx`
- `resources/js/components/notification-dropdown.tsx` - Header dropdown

**To Modify:**
- `resources/js/layouts/` - Add notification dropdown to header
- `routes/dashboard.php` - Add notification routes

## Implementation Steps

### 1. Create Notifications Page
- List all user notifications
- Filter by read/unread, category
- Mark as read/unread
- Delete notifications

### 2. Create Notification Components
- NotificationItem - Single notification display
- NotificationList - List with infinite scroll
- NotificationDropdown - Header bell icon with preview

### 3. Add to Layout
- Add notification bell to header
- Show unread count badge
- Dropdown with recent notifications

### 4. Add Dashboard Routes
- GET /dashboard/notifications - List page
- Inertia routes

## Todo List

- [ ] Create notifications index page
- [ ] Create NotificationItem component
- [ ] Create NotificationList component
- [ ] Create NotificationDropdown component
- [ ] Add to layout header
- [ ] Add dashboard routes

## Success Criteria

- [ ] Users can view notifications list
- [ ] Users can filter notifications
- [ ] Users can mark read/unread
- [ ] Users can delete notifications
- [ ] Header shows notification dropdown
