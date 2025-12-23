# Phase 5: Settings Page Integration

## Context

- Priority: Medium
- Status: Pending
- Dependencies: Phase 3 (User Preferences), Phase 4 (UI Components)

## Overview

Integrate notification preferences into existing settings page with per-category, per-channel toggles.

## Key Insights

- Existing settings page at `/resources/js/pages/settings/notifications/`
- Currently has placeholder form with no backend
- Need to connect to real preferences API
- Follow existing SettingsLayout pattern

## Requirements

### Functional
- Display all categories with their channels
- Toggle switches for each preference
- Show forced/locked preferences (security)
- Save button with optimistic updates
- Success/error toast feedback

### Non-functional
- Preserve existing page structure
- Consistent with other settings pages
- Loading states during save

## Related Code Files

### Modify
| File | Action | Description |
|------|--------|-------------|
| `resources/js/pages/settings/notifications/index.tsx` | Modify | Connect to API |
| `resources/js/pages/settings/notifications/notifications-form.tsx` | Modify | Use real preferences |

### Create
| File | Action | Description |
|------|--------|-------------|
| `resources/js/pages/settings/notifications/hooks/use-notification-preferences.ts` | Create | Preferences hook |

## Implementation Steps

### Step 1: Create Preferences Hook

```typescript
// resources/js/pages/settings/notifications/hooks/use-notification-preferences.ts
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { router } from '@inertiajs/react';
import { toast } from 'sonner';
import type { NotificationPreferences } from '@/types/notification';

interface PreferencesResponse {
  preferences: NotificationPreferences;
  channels: Record<string, {
    label: string;
    description: string;
    icon: string;
    enabled: boolean;
  }>;
}

export function useNotificationPreferences() {
  return useQuery<PreferencesResponse>({
    queryKey: ['notification-preferences'],
    queryFn: async () => {
      const response = await fetch('/dashboard/settings/notifications', {
        headers: {
          'Accept': 'application/json',
          'X-Inertia': 'true',
        },
      });
      const data = await response.json();
      return {
        preferences: data.props.preferences,
        channels: data.props.channels,
      };
    },
  });
}

export function useUpdateNotificationPreferences() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (preferences: Array<{
      category: string;
      channel: string;
      enabled: boolean;
    }>) => {
      return new Promise((resolve, reject) => {
        router.put('/dashboard/settings/notifications/preferences', {
          preferences,
        }, {
          preserveState: true,
          preserveScroll: true,
          onSuccess: () => resolve(true),
          onError: (errors) => reject(errors),
        });
      });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['notification-preferences'] });
      toast.success('Notification preferences saved');
    },
    onError: () => {
      toast.error('Failed to save preferences');
    },
  });
}
```

### Step 2: Update Notifications Form

```typescript
// resources/js/pages/settings/notifications/notifications-form.tsx
import { useState, useEffect } from 'react';
import { usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import {
  IconMessageCircle,
  IconMegaphone,
  IconShield,
  IconServer,
  IconReceipt,
  IconBell,
  IconMail,
  IconDeviceMobile,
  IconBellRinging,
  IconLock,
} from '@tabler/icons-react';
import { toast } from 'sonner';
import { router } from '@inertiajs/react';
import type { NotificationPreferences } from '@/types/notification';

const categoryIcons: Record<string, React.ComponentType<{ className?: string }>> = {
  communication: IconMessageCircle,
  marketing: IconMegaphone,
  security: IconShield,
  system: IconServer,
  transactional: IconReceipt,
};

const channelIcons: Record<string, React.ComponentType<{ className?: string }>> = {
  database: IconBell,
  email: IconMail,
  sms: IconDeviceMobile,
  push: IconBellRinging,
};

interface NotificationsFormProps {
  preferences: NotificationPreferences;
  channels: Record<string, {
    label: string;
    description: string;
    icon: string;
    enabled: boolean;
  }>;
}

export function NotificationsForm({ preferences, channels }: NotificationsFormProps) {
  const [localPrefs, setLocalPrefs] = useState<NotificationPreferences>(preferences);
  const [isDirty, setIsDirty] = useState(false);
  const [isSaving, setIsSaving] = useState(false);

  useEffect(() => {
    setLocalPrefs(preferences);
  }, [preferences]);

  const handleToggle = (category: string, channel: string, enabled: boolean) => {
    setLocalPrefs((prev) => ({
      ...prev,
      [category]: {
        ...prev[category],
        channels: {
          ...prev[category].channels,
          [channel]: {
            ...prev[category].channels[channel],
            enabled,
          },
        },
      },
    }));
    setIsDirty(true);
  };

  const handleSave = () => {
    setIsSaving(true);

    const preferencesToSave: Array<{
      category: string;
      channel: string;
      enabled: boolean;
    }> = [];

    Object.entries(localPrefs).forEach(([category, categoryData]) => {
      Object.entries(categoryData.channels).forEach(([channel, channelData]) => {
        if (channelData.configurable) {
          preferencesToSave.push({
            category,
            channel,
            enabled: channelData.enabled,
          });
        }
      });
    });

    router.put('/dashboard/settings/notifications/preferences', {
      preferences: preferencesToSave,
    }, {
      preserveState: true,
      preserveScroll: true,
      onSuccess: () => {
        setIsDirty(false);
        toast.success('Notification preferences saved');
      },
      onError: () => {
        toast.error('Failed to save preferences');
      },
      onFinish: () => {
        setIsSaving(false);
      },
    });
  };

  const enabledChannels = Object.entries(channels)
    .filter(([_, config]) => config.enabled)
    .map(([key]) => key);

  return (
    <div className="space-y-6">
      {Object.entries(localPrefs).map(([categoryKey, categoryData]) => {
        const CategoryIcon = categoryIcons[categoryKey] || IconBell;

        return (
          <Card key={categoryKey}>
            <CardHeader className="pb-3">
              <div className="flex items-center gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                  <CategoryIcon className="h-5 w-5 text-primary" />
                </div>
                <div className="flex-1">
                  <CardTitle className="text-base flex items-center gap-2">
                    {categoryData.label}
                    {!categoryData.user_configurable && (
                      <Badge variant="secondary" className="text-xs">
                        <IconLock className="h-3 w-3 mr-1" />
                        Required
                      </Badge>
                    )}
                  </CardTitle>
                  <CardDescription>{categoryData.description}</CardDescription>
                </div>
              </div>
            </CardHeader>
            <CardContent>
              <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {enabledChannels.map((channelKey) => {
                  const channelData = categoryData.channels[channelKey];
                  if (!channelData) return null;

                  const ChannelIcon = channelIcons[channelKey] || IconBell;
                  const channelConfig = channels[channelKey];
                  const isForced = channelData.forced;
                  const isConfigurable = channelData.configurable;

                  return (
                    <div
                      key={channelKey}
                      className="flex items-center justify-between rounded-lg border p-3"
                    >
                      <div className="flex items-center gap-3">
                        <ChannelIcon className="h-4 w-4 text-muted-foreground" />
                        <div>
                          <Label
                            htmlFor={`${categoryKey}-${channelKey}`}
                            className="text-sm font-medium"
                          >
                            {channelConfig.label}
                          </Label>
                          {isForced && (
                            <p className="text-xs text-muted-foreground">Always on</p>
                          )}
                        </div>
                      </div>
                      <Switch
                        id={`${categoryKey}-${channelKey}`}
                        checked={channelData.enabled}
                        onCheckedChange={(checked) =>
                          handleToggle(categoryKey, channelKey, checked)
                        }
                        disabled={!isConfigurable || isForced}
                        aria-readonly={!isConfigurable}
                      />
                    </div>
                  );
                })}
              </div>
            </CardContent>
          </Card>
        );
      })}

      <div className="flex justify-end">
        <Button
          onClick={handleSave}
          disabled={!isDirty || isSaving}
        >
          {isSaving ? 'Saving...' : 'Save preferences'}
        </Button>
      </div>
    </div>
  );
}
```

### Step 3: Update Index Page

```typescript
// resources/js/pages/settings/notifications/index.tsx
import { SettingLayout } from '@/layouts';
import ContentSection from '../components/content-section';
import { NotificationsForm } from './notifications-form';
import type { NotificationPreferences } from '@/types/notification';

interface Props {
  preferences: NotificationPreferences;
  channels: Record<string, {
    label: string;
    description: string;
    icon: string;
    enabled: boolean;
  }>;
}

export default function SettingsNotifications({ preferences, channels }: Props) {
  return (
    <SettingLayout title="Notifications Settings">
      <ContentSection
        title="Notifications"
        desc="Choose how and when you want to be notified about different types of activities."
      >
        <NotificationsForm preferences={preferences} channels={channels} />
      </ContentSection>
    </SettingLayout>
  );
}
```

## Todo List

- [ ] Create use-notification-preferences hook
- [ ] Update NotificationsForm component
- [ ] Update notifications index page
- [ ] Add loading skeleton for preferences
- [ ] Test toggle interactions
- [ ] Test save functionality
- [ ] Verify forced preferences display correctly
- [ ] Test mobile layout

## Success Criteria

- [ ] Preferences load from backend
- [ ] Toggles update local state
- [ ] Save button enabled when dirty
- [ ] Forced preferences are disabled
- [ ] Success toast on save
- [ ] Error handling on failure

## Risk Assessment

| Risk | Impact | Mitigation |
|------|--------|------------|
| Optimistic updates fail | Low | Revert on error, show toast |
| Large number of preferences | Low | Grid layout, collapsible cards |

## Security Considerations

- Validate preferences on backend
- Don't allow disabling forced preferences via API bypass
- CSRF protection on PUT request

## Next Steps

→ Phase 6: API Endpoints & Controllers
