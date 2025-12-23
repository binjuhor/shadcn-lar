# Phase 4: Notification Center UI

## Context

- Priority: Medium
- Status: Pending
- Dependencies: Phase 1 (Database), Phase 6 (API)

## Overview

React components for notification bell icon, dropdown, and full notification center page with polling-based updates.

## Key Insights

- Follow existing shadcn-ui patterns from mail/users modules
- Use TanStack Query for data fetching with polling
- Sonner for toast notifications
- Tabler icons for consistency

## Requirements

### Functional
- Bell icon in header with unread count badge
- Dropdown with recent notifications
- Full notification center page
- Mark as read (single/all)
- Delete notifications
- Filter by category
- Infinite scroll or pagination

### Non-functional
- 30-second polling interval
- Optimistic updates for read/delete
- Mobile responsive
- Accessible (ARIA)

## Architecture

### Component Structure

```
resources/js/
├── components/
│   └── layout/
│       └── notification-bell.tsx           # Header bell icon
├── pages/
│   └── notifications/
│       ├── index.tsx                       # Main page
│       ├── components/
│       │   ├── notification-list.tsx       # Notification list
│       │   ├── notification-item.tsx       # Single notification
│       │   ├── notification-dropdown.tsx   # Header dropdown
│       │   ├── notification-filters.tsx    # Category filters
│       │   └── notification-empty.tsx      # Empty state
│       ├── context/
│       │   └── notifications-context.tsx   # State management
│       ├── data/
│       │   └── schema.ts                   # Zod schemas
│       └── hooks/
│           └── use-notifications.ts        # Data fetching hook
├── types/
│   └── notification.d.ts                   # Type definitions
```

## Related Code Files

### Create
| File | Action | Description |
|------|--------|-------------|
| `resources/js/types/notification.d.ts` | Create | Type definitions |
| `resources/js/pages/notifications/index.tsx` | Create | Main page |
| `resources/js/pages/notifications/data/schema.ts` | Create | Zod schemas |
| `resources/js/pages/notifications/hooks/use-notifications.ts` | Create | Data hook |
| `resources/js/pages/notifications/context/notifications-context.tsx` | Create | Context |
| `resources/js/pages/notifications/components/notification-list.tsx` | Create | List component |
| `resources/js/pages/notifications/components/notification-item.tsx` | Create | Item component |
| `resources/js/pages/notifications/components/notification-dropdown.tsx` | Create | Dropdown |
| `resources/js/pages/notifications/components/notification-filters.tsx` | Create | Filters |
| `resources/js/pages/notifications/components/notification-empty.tsx` | Create | Empty state |
| `resources/js/components/layout/notification-bell.tsx` | Create | Bell icon |

### Modify
| File | Action | Description |
|------|--------|-------------|
| `resources/js/components/layout/header.tsx` | Modify | Add notification bell |
| `resources/js/components/layout/data/sidebar-data.ts` | Modify | Add nav item |

## Implementation Steps

### Step 1: Create Type Definitions

```typescript
// resources/js/types/notification.d.ts
export interface Notification {
  id: string;
  type: string;
  data: {
    category: NotificationCategory;
    title: string;
    message: string;
    action_url?: string;
    action_text?: string;
    icon?: string;
    [key: string]: unknown;
  };
  read_at: string | null;
  created_at: string;
}

export type NotificationCategory =
  | 'communication'
  | 'marketing'
  | 'security'
  | 'system'
  | 'transactional';

export interface NotificationPreferences {
  [category: string]: {
    label: string;
    description: string;
    icon: string;
    user_configurable: boolean;
    force_enabled: string[];
    channels: {
      [channel: string]: {
        enabled: boolean;
        configurable: boolean;
        forced: boolean;
      };
    };
  };
}

export interface NotificationsResponse {
  data: Notification[];
  unread_count: number;
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}
```

### Step 2: Create Zod Schema

```typescript
// resources/js/pages/notifications/data/schema.ts
import { z } from 'zod';

export const notificationCategorySchema = z.enum([
  'communication',
  'marketing',
  'security',
  'system',
  'transactional',
]);

export const notificationDataSchema = z.object({
  category: notificationCategorySchema,
  title: z.string(),
  message: z.string(),
  action_url: z.string().nullable().optional(),
  action_text: z.string().nullable().optional(),
  icon: z.string().nullable().optional(),
});

export const notificationSchema = z.object({
  id: z.string(),
  type: z.string(),
  data: notificationDataSchema,
  read_at: z.string().nullable(),
  created_at: z.string(),
});

export const notificationsResponseSchema = z.object({
  data: z.array(notificationSchema),
  unread_count: z.number(),
  current_page: z.number(),
  last_page: z.number(),
  per_page: z.number(),
  total: z.number(),
});

export type NotificationData = z.infer<typeof notificationDataSchema>;
export type NotificationItem = z.infer<typeof notificationSchema>;
```

### Step 3: Create Data Fetching Hook

```typescript
// resources/js/pages/notifications/hooks/use-notifications.ts
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import axios from '@/lib/axios';
import type { NotificationsResponse } from '@/types/notification';

const POLLING_INTERVAL = 30000; // 30 seconds

export function useNotifications(options?: {
  category?: string;
  page?: number;
  enabled?: boolean;
}) {
  const { category, page = 1, enabled = true } = options ?? {};

  return useQuery<NotificationsResponse>({
    queryKey: ['notifications', { category, page }],
    queryFn: async () => {
      const params = new URLSearchParams();
      if (category) params.set('category', category);
      params.set('page', String(page));

      const { data } = await axios.get(`/api/notifications?${params}`);
      return data;
    },
    enabled,
    refetchInterval: POLLING_INTERVAL,
    staleTime: 10000,
  });
}

export function useUnreadCount() {
  return useQuery<number>({
    queryKey: ['notifications', 'unread-count'],
    queryFn: async () => {
      const { data } = await axios.get('/api/notifications/unread-count');
      return data.count;
    },
    refetchInterval: POLLING_INTERVAL,
    staleTime: 10000,
  });
}

export function useMarkAsRead() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (id: string) => {
      await axios.post(`/api/notifications/${id}/read`);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['notifications'] });
    },
  });
}

export function useMarkAllAsRead() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async () => {
      await axios.post('/api/notifications/read-all');
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['notifications'] });
    },
  });
}

export function useDeleteNotification() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (id: string) => {
      await axios.delete(`/api/notifications/${id}`);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['notifications'] });
    },
  });
}
```

### Step 4: Create Notification Item Component

```typescript
// resources/js/pages/notifications/components/notification-item.tsx
import { formatDistanceToNow } from 'date-fns';
import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import {
  IconBell,
  IconMail,
  IconShield,
  IconServer,
  IconReceipt,
  IconCheck,
  IconTrash,
  IconMessageCircle,
} from '@tabler/icons-react';
import type { NotificationItem as NotificationType } from '../data/schema';

const categoryIcons: Record<string, React.ComponentType<{ className?: string }>> = {
  communication: IconMessageCircle,
  marketing: IconBell,
  security: IconShield,
  system: IconServer,
  transactional: IconReceipt,
};

interface NotificationItemProps {
  notification: NotificationType;
  onMarkAsRead: (id: string) => void;
  onDelete: (id: string) => void;
}

export function NotificationItem({
  notification,
  onMarkAsRead,
  onDelete,
}: NotificationItemProps) {
  const Icon = categoryIcons[notification.data.category] || IconBell;
  const isUnread = !notification.read_at;

  return (
    <div
      className={cn(
        'flex items-start gap-4 p-4 border-b transition-colors hover:bg-muted/50',
        isUnread && 'bg-muted/30'
      )}
    >
      <div className={cn(
        'flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center',
        isUnread ? 'bg-primary/10 text-primary' : 'bg-muted text-muted-foreground'
      )}>
        <Icon className="h-5 w-5" />
      </div>

      <div className="flex-1 min-w-0">
        <div className="flex items-center gap-2">
          <p className={cn(
            'text-sm font-medium truncate',
            isUnread && 'text-foreground'
          )}>
            {notification.data.title}
          </p>
          {isUnread && (
            <span className="flex-shrink-0 w-2 h-2 bg-primary rounded-full" />
          )}
        </div>

        <p className="text-sm text-muted-foreground line-clamp-2 mt-1">
          {notification.data.message}
        </p>

        <div className="flex items-center gap-2 mt-2">
          <span className="text-xs text-muted-foreground">
            {formatDistanceToNow(new Date(notification.created_at), { addSuffix: true })}
          </span>

          {notification.data.action_url && (
            <Link
              href={notification.data.action_url}
              className="text-xs text-primary hover:underline"
            >
              {notification.data.action_text || 'View'}
            </Link>
          )}
        </div>
      </div>

      <div className="flex-shrink-0 flex items-center gap-1">
        {isUnread && (
          <Button
            variant="ghost"
            size="icon"
            className="h-8 w-8"
            onClick={() => onMarkAsRead(notification.id)}
          >
            <IconCheck className="h-4 w-4" />
            <span className="sr-only">Mark as read</span>
          </Button>
        )}
        <Button
          variant="ghost"
          size="icon"
          className="h-8 w-8 text-destructive hover:text-destructive"
          onClick={() => onDelete(notification.id)}
        >
          <IconTrash className="h-4 w-4" />
          <span className="sr-only">Delete</span>
        </Button>
      </div>
    </div>
  );
}
```

### Step 5: Create Notification Bell Component

```typescript
// resources/js/components/layout/notification-bell.tsx
import { useState } from 'react';
import { Link } from '@inertiajs/react';
import { IconBell } from '@tabler/icons-react';
import { Button } from '@/components/ui/button';
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover';
import { ScrollArea } from '@/components/ui/scroll-area';
import { useNotifications, useUnreadCount, useMarkAsRead } from '@/pages/notifications/hooks/use-notifications';
import { NotificationItem } from '@/pages/notifications/components/notification-item';

export function NotificationBell() {
  const [open, setOpen] = useState(false);
  const { data: unreadCount = 0 } = useUnreadCount();
  const { data: notifications } = useNotifications({ enabled: open });
  const markAsRead = useMarkAsRead();

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <Button variant="ghost" size="icon" className="relative">
          <IconBell className="h-5 w-5" />
          {unreadCount > 0 && (
            <span className="absolute -top-1 -right-1 h-5 w-5 rounded-full bg-destructive text-destructive-foreground text-xs flex items-center justify-center">
              {unreadCount > 99 ? '99+' : unreadCount}
            </span>
          )}
          <span className="sr-only">Notifications</span>
        </Button>
      </PopoverTrigger>
      <PopoverContent className="w-80 p-0" align="end">
        <div className="flex items-center justify-between p-4 border-b">
          <h4 className="font-semibold">Notifications</h4>
          <Link
            href="/dashboard/notifications"
            className="text-sm text-primary hover:underline"
            onClick={() => setOpen(false)}
          >
            View all
          </Link>
        </div>
        <ScrollArea className="h-[300px]">
          {notifications?.data.length === 0 ? (
            <div className="p-4 text-center text-muted-foreground">
              No notifications
            </div>
          ) : (
            notifications?.data.slice(0, 5).map((notification) => (
              <NotificationItem
                key={notification.id}
                notification={notification}
                onMarkAsRead={(id) => markAsRead.mutate(id)}
                onDelete={() => {}}
              />
            ))
          )}
        </ScrollArea>
      </PopoverContent>
    </Popover>
  );
}
```

### Step 6: Create Main Notifications Page

```typescript
// resources/js/pages/notifications/index.tsx
import { useState } from 'react';
import { AuthenticatedLayout } from '@/layouts';
import { Main } from '@/components/layout/main';
import { Button } from '@/components/ui/button';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { IconCheck, IconTrash } from '@tabler/icons-react';
import {
  useNotifications,
  useMarkAllAsRead,
  useMarkAsRead,
  useDeleteNotification,
} from './hooks/use-notifications';
import { NotificationItem } from './components/notification-item';
import { NotificationEmpty } from './components/notification-empty';
import {
  Pagination,
  PaginationContent,
  PaginationItem,
  PaginationLink,
  PaginationNext,
  PaginationPrevious,
} from '@/components/ui/pagination';

const categories = [
  { value: '', label: 'All' },
  { value: 'communication', label: 'Communication' },
  { value: 'marketing', label: 'Marketing' },
  { value: 'security', label: 'Security' },
  { value: 'system', label: 'System' },
  { value: 'transactional', label: 'Transactional' },
];

export default function Notifications() {
  const [category, setCategory] = useState('');
  const [page, setPage] = useState(1);

  const { data, isLoading } = useNotifications({ category, page });
  const markAsRead = useMarkAsRead();
  const markAllAsRead = useMarkAllAsRead();
  const deleteNotification = useDeleteNotification();

  return (
    <AuthenticatedLayout title="Notifications">
      <Main>
        <div className="flex items-center justify-between mb-6">
          <div>
            <h2 className="text-2xl font-bold tracking-tight">Notifications</h2>
            <p className="text-muted-foreground">
              {data?.unread_count ?? 0} unread notifications
            </p>
          </div>
          <div className="flex gap-2">
            <Button
              variant="outline"
              onClick={() => markAllAsRead.mutate()}
              disabled={!data?.unread_count}
            >
              <IconCheck className="mr-2 h-4 w-4" />
              Mark all read
            </Button>
          </div>
        </div>

        <Tabs value={category} onValueChange={(v) => { setCategory(v); setPage(1); }}>
          <TabsList className="mb-4">
            {categories.map((cat) => (
              <TabsTrigger key={cat.value} value={cat.value}>
                {cat.label}
              </TabsTrigger>
            ))}
          </TabsList>
        </Tabs>

        <div className="rounded-lg border">
          {isLoading ? (
            <div className="p-8 text-center text-muted-foreground">
              Loading...
            </div>
          ) : data?.data.length === 0 ? (
            <NotificationEmpty category={category} />
          ) : (
            data?.data.map((notification) => (
              <NotificationItem
                key={notification.id}
                notification={notification}
                onMarkAsRead={(id) => markAsRead.mutate(id)}
                onDelete={(id) => deleteNotification.mutate(id)}
              />
            ))
          )}
        </div>

        {data && data.last_page > 1 && (
          <Pagination className="mt-4">
            <PaginationContent>
              <PaginationItem>
                <PaginationPrevious
                  onClick={() => setPage((p) => Math.max(1, p - 1))}
                  aria-disabled={page === 1}
                />
              </PaginationItem>
              {Array.from({ length: data.last_page }, (_, i) => i + 1).map((p) => (
                <PaginationItem key={p}>
                  <PaginationLink
                    onClick={() => setPage(p)}
                    isActive={page === p}
                  >
                    {p}
                  </PaginationLink>
                </PaginationItem>
              ))}
              <PaginationItem>
                <PaginationNext
                  onClick={() => setPage((p) => Math.min(data.last_page, p + 1))}
                  aria-disabled={page === data.last_page}
                />
              </PaginationItem>
            </PaginationContent>
          </Pagination>
        )}
      </Main>
    </AuthenticatedLayout>
  );
}
```

### Step 7: Create Empty State Component

```typescript
// resources/js/pages/notifications/components/notification-empty.tsx
import { IconBellOff } from '@tabler/icons-react';

interface NotificationEmptyProps {
  category?: string;
}

export function NotificationEmpty({ category }: NotificationEmptyProps) {
  return (
    <div className="flex flex-col items-center justify-center py-12 text-center">
      <IconBellOff className="h-12 w-12 text-muted-foreground/50 mb-4" />
      <h3 className="text-lg font-medium">No notifications</h3>
      <p className="text-sm text-muted-foreground mt-1">
        {category
          ? `You don't have any ${category} notifications yet.`
          : "You're all caught up!"}
      </p>
    </div>
  );
}
```

## Todo List

- [ ] Create notification type definitions
- [ ] Create Zod schemas
- [ ] Create use-notifications hook
- [ ] Create NotificationItem component
- [ ] Create NotificationEmpty component
- [ ] Create NotificationBell component
- [ ] Create Notifications page
- [ ] Add bell to header
- [ ] Add notification route to sidebar
- [ ] Test polling behavior
- [ ] Test mobile responsiveness

## Success Criteria

- [ ] Bell icon shows unread count
- [ ] Dropdown shows recent notifications
- [ ] Full page lists all notifications
- [ ] Category filtering works
- [ ] Mark as read updates instantly
- [ ] Polling updates every 30 seconds
- [ ] Mobile layout is responsive

## Risk Assessment

| Risk | Impact | Mitigation |
|------|--------|------------|
| Polling too frequent | Medium | 30s interval, staleTime |
| Large notification lists | Medium | Pagination, virtualization if needed |

## Security Considerations

- Sanitize notification content before rendering
- Verify action URLs are from trusted domains
- Don't expose notification IDs in predictable pattern

## Next Steps

→ Phase 5: Settings Page Integration
