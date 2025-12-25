# Phase 04: Cleanup & Verify

## Context Links

- Parent: [plan.md](./plan.md)
- Previous: [phase-03-update-routes.md](./phase-03-update-routes.md)

## Overview

- **Date:** 2025-12-25
- **Priority:** High
- **Description:** Delete original files and verify migration
- **Implementation Status:** Pending
- **Review Status:** Pending

## Key Insights

- Must verify routes work before deleting originals
- Check for any other references to old namespaces

## Requirements

### Functional
- Delete all migrated files from app/
- Verify routes work correctly

### Non-functional
- No broken imports anywhere
- Application boots without errors

## Architecture

Files to delete:
```
app/Http/Controllers/SettingsController.php
app/Http/Controllers/ProfileController.php
app/Http/Requests/Settings/ (entire folder)
app/Http/Requests/ProfileUpdateRequest.php
```

## Related Code Files

**Delete:**
- `app/Http/Controllers/SettingsController.php`
- `app/Http/Controllers/ProfileController.php`
- `app/Http/Requests/Settings/UpdateAccountRequest.php`
- `app/Http/Requests/Settings/UpdateAppearanceRequest.php`
- `app/Http/Requests/Settings/UpdateDisplayRequest.php`
- `app/Http/Requests/Settings/UpdateNotificationsRequest.php`
- `app/Http/Requests/Settings/UpdateProfileRequest.php`
- `app/Http/Requests/ProfileUpdateRequest.php`

## Implementation Steps

1. Run `php artisan route:list --path=settings` - verify routes use module controllers
2. Run `php artisan route:list --path=profile` - verify profile routes
3. Run `php artisan config:clear && php artisan cache:clear`
4. Delete `app/Http/Controllers/SettingsController.php`
5. Delete `app/Http/Controllers/ProfileController.php`
6. Delete `app/Http/Requests/Settings/` directory
7. Delete `app/Http/Requests/ProfileUpdateRequest.php`
8. Run `composer dump-autoload`
9. Test application loads without errors

## Todo List

- [ ] Verify routes with artisan
- [ ] Clear caches
- [ ] Delete SettingsController from app
- [ ] Delete ProfileController from app
- [ ] Delete Settings requests folder
- [ ] Delete ProfileUpdateRequest
- [ ] Run composer dump-autoload
- [ ] Test application

## Success Criteria

- Application boots without errors
- All settings pages accessible
- Profile edit/update/delete works
- No references to deleted files

## Risk Assessment

| Risk | Impact | Mitigation |
|------|--------|------------|
| Broken references | High | Search codebase for old namespaces before delete |
| Cache issues | Medium | Clear all caches |

## Security Considerations

None - cleanup phase only

## Next Steps

Migration complete. Update plan.md status to completed.
