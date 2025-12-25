# Phase 01: Move Request Classes

## Context Links

- Parent: [plan.md](./plan.md)
- Next: [phase-02-update-controllers.md](./phase-02-update-controllers.md)

## Overview

- **Date:** 2025-12-25
- **Priority:** High
- **Description:** Create Requests folder in Settings module and move all request classes
- **Implementation Status:** Pending
- **Review Status:** Pending

## Key Insights

- 5 request classes in `app/Http/Requests/Settings/`
- 1 request class in `app/Http/Requests/ProfileUpdateRequest.php`
- All use `Illuminate\Foundation\Http\FormRequest`
- Need namespace change from `App\Http\Requests` to `Modules\Settings\Http\Requests`

## Requirements

### Functional
- Create `Modules/Settings/Http/Requests/` directory
- Move 6 request files with updated namespaces

### Non-functional
- Maintain validation rules exactly
- Keep authorize() methods

## Architecture

```
Modules/Settings/Http/Requests/
├── ProfileUpdateRequest.php
├── UpdateAccountRequest.php
├── UpdateAppearanceRequest.php
├── UpdateDisplayRequest.php
├── UpdateNotificationsRequest.php
└── UpdateProfileRequest.php
```

## Related Code Files

**Source (delete after):**
- `app/Http/Requests/Settings/UpdateAccountRequest.php`
- `app/Http/Requests/Settings/UpdateAppearanceRequest.php`
- `app/Http/Requests/Settings/UpdateDisplayRequest.php`
- `app/Http/Requests/Settings/UpdateNotificationsRequest.php`
- `app/Http/Requests/Settings/UpdateProfileRequest.php`
- `app/Http/Requests/ProfileUpdateRequest.php`

**Target:**
- `Modules/Settings/Http/Requests/` (create all files)

## Implementation Steps

1. Create `Modules/Settings/Http/Requests/` directory
2. Create UpdateAccountRequest.php with namespace `Modules\Settings\Http\Requests`
3. Create UpdateAppearanceRequest.php with namespace `Modules\Settings\Http\Requests`
4. Create UpdateDisplayRequest.php with namespace `Modules\Settings\Http\Requests`
5. Create UpdateNotificationsRequest.php with namespace `Modules\Settings\Http\Requests`
6. Create UpdateProfileRequest.php with namespace `Modules\Settings\Http\Requests`
7. Create ProfileUpdateRequest.php with namespace `Modules\Settings\Http\Requests`

## Todo List

- [ ] Create Requests directory
- [ ] Move UpdateAccountRequest
- [ ] Move UpdateAppearanceRequest
- [ ] Move UpdateDisplayRequest
- [ ] Move UpdateNotificationsRequest
- [ ] Move UpdateProfileRequest
- [ ] Move ProfileUpdateRequest

## Success Criteria

- All 6 request files exist in module
- Namespaces updated correctly
- Validation rules preserved

## Risk Assessment

| Risk | Impact | Mitigation |
|------|--------|------------|
| Missing User import | Medium | Ensure `App\Models\User` imported in ProfileUpdateRequest |

## Security Considerations

- authorize() methods must return true for authenticated users
- Validation rules prevent injection

## Next Steps

Proceed to Phase 02: Update Controllers
