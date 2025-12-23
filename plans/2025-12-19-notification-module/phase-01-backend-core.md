# Phase 01: Backend Core

**Status:** Pending
**Priority:** High

## Context

- [Main Plan](./plan.md)
- Existing module patterns: `Modules/Blog/`, `Modules/Permission/`

## Overview

Set up core backend components for the Notification module following established patterns.

## Related Files

**To Create:**
- `Modules/Notification/app/Models/Notification.php`
- `Modules/Notification/app/Models/NotificationTemplate.php`
- `Modules/Notification/app/Http/Controllers/NotificationController.php`
- `Modules/Notification/app/Http/Resources/NotificationResource.php`
- `Modules/Notification/app/Http/Resources/NotificationTemplateResource.php`
- `Modules/Notification/app/Policies/NotificationPolicy.php`
- `Modules/Notification/app/Policies/NotificationTemplatePolicy.php`
- `Modules/Notification/database/migrations/*_create_notification_templates_table.php`

**To Modify:**
- `Modules/Notification/app/Providers/NotificationServiceProvider.php` - Add policies
- `Modules/Notification/routes/api.php` - Add routes

**Reference:**
- `Modules/Blog/app/Models/Post.php`
- `Modules/Blog/app/Http/Controllers/PostController.php`
- `Modules/Blog/app/Policies/PostPolicy.php`

## Implementation Steps

### 1. Create Notification Model
- Wrap Laravel's DatabaseNotification
- Add scopes for filtering (unread, by category)
- Add relationships (notifiable, user)

### 2. Create NotificationTemplate Model
- Fields: name, subject, body, category, channel, variables, is_active
- Factories and seeders

### 3. Create Migration for Templates
- notification_templates table

### 4. Create Resources
- NotificationResource - transform notification data
- NotificationTemplateResource - transform template data

### 5. Create Policies
- NotificationPolicy - users can only manage their own
- NotificationTemplatePolicy - admin only

### 6. Update ServiceProvider
- Register policies
- Register routes

### 7. Create Controllers
- NotificationController (CRUD for user notifications)
- NotificationTemplateController (CRUD for admin)

## Todo List

- [ ] Create Notification model
- [ ] Create NotificationTemplate model
- [ ] Create notification_templates migration
- [ ] Create NotificationResource
- [ ] Create NotificationTemplateResource
- [ ] Create NotificationPolicy
- [ ] Create NotificationTemplatePolicy
- [ ] Update NotificationServiceProvider
- [ ] Create/Update NotificationController
- [ ] Create NotificationTemplateController
- [ ] Update routes/api.php

## Success Criteria

- [ ] Models created with proper relationships
- [ ] Migrations run without errors
- [ ] Policies properly authorize actions
- [ ] Routes accessible with proper middleware

## Security Considerations

- Users can only access their own notifications
- Admin-only access for templates
- Input validation on all endpoints
