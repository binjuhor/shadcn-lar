# Phase 02: Admin Features

**Status:** Pending
**Priority:** High

## Context

- [Main Plan](./plan.md)
- [Phase 01: Backend Core](./phase-01-backend-core.md)

## Overview

Implement admin features for sending notifications and managing templates.

## Related Files

**To Create:**
- `Modules/Notification/app/Http/Controllers/AdminNotificationController.php`
- `Modules/Notification/app/Notifications/GenericNotification.php`
- `Modules/Notification/app/Services/NotificationService.php`

**To Modify:**
- `Modules/Notification/routes/api.php` - Add admin routes

## Implementation Steps

### 1. Create NotificationService
- Send notifications to users/groups
- Use templates or custom content
- Support multiple channels

### 2. Create GenericNotification
- Flexible notification class
- Support all channels (database, email, sms, push)
- Use templates for content

### 3. Create AdminNotificationController
- Send notification to user(s)
- Send notification to role(s)
- Broadcast to all users
- View sent notifications history

### 4. Update Routes
- POST /v1/notification/admin/send - Send notification
- GET /v1/notification/admin/history - Sent history

## Todo List

- [ ] Create NotificationService
- [ ] Create GenericNotification class
- [ ] Create AdminNotificationController
- [ ] Add admin routes
- [ ] Test notification sending

## Success Criteria

- [ ] Admin can send notifications to individual users
- [ ] Admin can send notifications to roles
- [ ] Admin can broadcast to all users
- [ ] Notifications delivered via correct channels
