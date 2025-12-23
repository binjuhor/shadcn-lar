# Phase 02: Backend Integration

## Context Links
- Parent: [plan.md](./plan.md)
- Depends on: [phase-01-frontend-restructure.md](./phase-01-frontend-restructure.md)
- Reference: `app/Http/Controllers/ProfileController.php`

## Overview

| Field | Value |
|-------|-------|
| Date | 2024-12-19 |
| Priority | High |
| Implementation Status | Pending |
| Review Status | Pending |

**Description:** Create SettingsController with API endpoints for each settings section, connect frontend forms to backend via Inertia.

## Key Insights

1. Existing `ProfileController` handles basic profile CRUD
2. Forms currently submit to toast only - no persistence
3. Settings should be stored in user table or separate settings table
4. Inertia handles form submissions via `useForm` hook
5. Laravel FormRequest classes should validate input

## Requirements

### Functional
- SettingsController with index, update methods per section
- Form submissions persist to database
- Validation via FormRequest classes
- Success/error feedback via flash messages

### Non-Functional
- Follow Laravel controller conventions
- Use Inertia form handling
- Proper error handling

## Architecture

```
app/
├── Http/
│   ├── Controllers/
│   │   └── SettingsController.php          # NEW
│   └── Requests/
│       └── Settings/
│           ├── UpdateProfileRequest.php    # NEW
│           ├── UpdateAccountRequest.php    # NEW
│           ├── UpdateAppearanceRequest.php # NEW
│           ├── UpdateNotificationsRequest.php # NEW
│           └── UpdateDisplayRequest.php    # NEW
└── Models/
    └── User.php                            # MODIFY - add settings fields/relation
```

## Related Code Files

### CREATE
| File | Description |
|------|-------------|
| `app/Http/Controllers/SettingsController.php` | Settings CRUD controller |
| `app/Http/Requests/Settings/UpdateProfileRequest.php` | Profile validation |
| `app/Http/Requests/Settings/UpdateAccountRequest.php` | Account validation |
| `app/Http/Requests/Settings/UpdateAppearanceRequest.php` | Appearance validation |
| `app/Http/Requests/Settings/UpdateNotificationsRequest.php` | Notifications validation |
| `app/Http/Requests/Settings/UpdateDisplayRequest.php` | Display validation |

### MODIFY
| File | Changes |
|------|---------|
| `app/Models/User.php` | Add settings-related fields or relation |
| `resources/js/pages/settings/*/index.tsx` | Connect to API |
| `resources/js/pages/settings/*-form.tsx` | Use Inertia useForm |

## Implementation Steps

### Step 1: Create SettingsController
Create `app/Http/Controllers/SettingsController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\Settings\UpdateProfileRequest;
use App\Http\Requests\Settings\UpdateAccountRequest;
use App\Http\Requests\Settings\UpdateAppearanceRequest;
use App\Http\Requests\Settings\UpdateNotificationsRequest;
use App\Http\Requests\Settings\UpdateDisplayRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function profile(): Response
    {
        return Inertia::render('settings/profile/index', [
            'settings' => auth()->user()->only(['username', 'email', 'bio', 'urls']),
        ]);
    }

    public function updateProfile(UpdateProfileRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return back()->with('success', 'Profile updated successfully.');
    }

    public function account(): Response
    {
        return Inertia::render('settings/account/index', [
            'settings' => auth()->user()->only(['name', 'dob', 'language']),
        ]);
    }

    public function updateAccount(UpdateAccountRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return back()->with('success', 'Account updated successfully.');
    }

    public function appearance(): Response
    {
        return Inertia::render('settings/appearance/index', [
            'settings' => auth()->user()->settings ?? ['theme' => 'light', 'font' => 'inter'],
        ]);
    }

    public function updateAppearance(UpdateAppearanceRequest $request): RedirectResponse
    {
        $request->user()->update([
            'settings' => array_merge(
                $request->user()->settings ?? [],
                $request->validated()
            ),
        ]);

        return back()->with('success', 'Appearance updated successfully.');
    }

    public function notifications(): Response
    {
        return Inertia::render('settings/notifications/index', [
            'settings' => auth()->user()->notification_settings ?? [],
        ]);
    }

    public function updateNotifications(UpdateNotificationsRequest $request): RedirectResponse
    {
        $request->user()->update([
            'notification_settings' => $request->validated(),
        ]);

        return back()->with('success', 'Notification preferences updated successfully.');
    }

    public function display(): Response
    {
        return Inertia::render('settings/display/index', [
            'settings' => auth()->user()->display_settings ?? ['items' => []],
        ]);
    }

    public function updateDisplay(UpdateDisplayRequest $request): RedirectResponse
    {
        $request->user()->update([
            'display_settings' => $request->validated(),
        ]);

        return back()->with('success', 'Display settings updated successfully.');
    }
}
```

### Step 2: Create FormRequest Classes
Create `app/Http/Requests/Settings/UpdateProfileRequest.php`:

```php
<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'min:2', 'max:30', Rule::unique('users')->ignore($this->user()->id)],
            'email' => ['required', 'email', Rule::unique('users')->ignore($this->user()->id)],
            'bio' => ['nullable', 'string', 'max:160'],
            'urls' => ['nullable', 'array'],
            'urls.*.value' => ['required_with:urls', 'url'],
        ];
    }
}
```

Similar patterns for other requests (UpdateAccountRequest, UpdateAppearanceRequest, etc.)

### Step 3: Update User Model
Add to `app/Models/User.php`:

```php
protected $casts = [
    // ... existing casts
    'settings' => 'array',
    'notification_settings' => 'array',
    'display_settings' => 'array',
    'dob' => 'date',
];

protected $fillable = [
    // ... existing fillables
    'username',
    'bio',
    'urls',
    'language',
    'dob',
    'settings',
    'notification_settings',
    'display_settings',
];
```

### Step 4: Create Database Migration
Create migration for new settings fields:

```php
Schema::table('users', function (Blueprint $table) {
    $table->string('username')->nullable()->unique()->after('name');
    $table->text('bio')->nullable()->after('email');
    $table->json('urls')->nullable()->after('bio');
    $table->string('language')->default('en')->after('urls');
    $table->date('dob')->nullable()->after('language');
    $table->json('settings')->nullable()->after('password');
    $table->json('notification_settings')->nullable()->after('settings');
    $table->json('display_settings')->nullable()->after('notification_settings');
});
```

### Step 5: Update Frontend Forms
Update forms to use Inertia's `useForm`. Example for `profile-form.tsx`:

```tsx
import { useForm } from '@inertiajs/react'
import { profileFormSchema, type ProfileFormValues } from '../data/schema'

interface Props {
  initialData?: Partial<ProfileFormValues>
}

export default function ProfileForm({ initialData }: Props) {
  const { data, setData, patch, processing, errors } = useForm({
    username: initialData?.username ?? '',
    email: initialData?.email ?? '',
    bio: initialData?.bio ?? '',
    urls: initialData?.urls ?? [],
  })

  function onSubmit(e: React.FormEvent) {
    e.preventDefault()
    patch('/dashboard/settings/profile', {
      preserveScroll: true,
      onSuccess: () => {
        toast({ title: 'Profile updated successfully.' })
      },
    })
  }

  // ... rest of form
}
```

## Todo List

- [ ] Create SettingsController.php
- [ ] Create UpdateProfileRequest.php
- [ ] Create UpdateAccountRequest.php
- [ ] Create UpdateAppearanceRequest.php
- [ ] Create UpdateNotificationsRequest.php
- [ ] Create UpdateDisplayRequest.php
- [ ] Update User model with casts and fillables
- [ ] Create migration for settings fields
- [ ] Run migration
- [ ] Update profile-form.tsx to use Inertia useForm
- [ ] Update account-form.tsx to use Inertia useForm
- [ ] Update appearance-form.tsx to use Inertia useForm
- [ ] Update notifications-form.tsx to use Inertia useForm
- [ ] Update display-form.tsx to use Inertia useForm
- [ ] Test each form submission

## Success Criteria

1. All forms submit to backend API
2. Data persists to database
3. Validation errors display correctly
4. Success messages shown via toast
5. No breaking changes to existing auth

## Risk Assessment

| Risk | Impact | Mitigation |
|------|--------|------------|
| Migration conflicts | High | Test on fresh DB first |
| Auth user data changes | High | Keep existing fields unchanged |
| Validation mismatches | Medium | Mirror Zod schemas in FormRequest |

## Security Considerations

- All routes require authentication middleware
- FormRequest handles authorization
- Sanitize JSON fields before storage
- Validate file uploads if added later

## Next Steps

After Phase 2 completion:
1. Proceed to Phase 3: Routes & Cleanup
2. Update route definitions
3. Fix route naming conventions
