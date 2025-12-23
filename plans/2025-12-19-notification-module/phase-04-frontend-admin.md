# Phase 04: Frontend Admin

**Status:** Pending
**Priority:** Medium

## Context

- [Main Plan](./plan.md)
- [Phase 02: Admin Features](./phase-02-admin-features.md)

## Overview

Implement admin frontend for sending notifications and managing templates.

## Related Files

**To Create:**
- `resources/js/pages/notifications/templates/index.tsx` - Templates list
- `resources/js/pages/notifications/templates/create.tsx` - Create template
- `resources/js/pages/notifications/templates/edit.tsx` - Edit template
- `resources/js/pages/notifications/send/index.tsx` - Send notification form

**To Modify:**
- `routes/dashboard.php` - Add admin routes

## Implementation Steps

### 1. Create Templates Management
- List templates with filters
- Create new template form
- Edit template form
- Delete template

### 2. Create Send Notification Page
- Select recipients (users, roles, all)
- Select template or custom content
- Select channels
- Preview before sending

### 3. Add Dashboard Routes
- GET /dashboard/notifications/templates - Templates list
- GET /dashboard/notifications/templates/create - Create form
- GET /dashboard/notifications/templates/{id}/edit - Edit form
- GET /dashboard/notifications/send - Send form

## Todo List

- [ ] Create templates index page
- [ ] Create template create page
- [ ] Create template edit page
- [ ] Create send notification page
- [ ] Add dashboard routes

## Success Criteria

- [ ] Admin can manage templates
- [ ] Admin can send notifications
- [ ] Admin can preview notifications
- [ ] Proper authorization checks
