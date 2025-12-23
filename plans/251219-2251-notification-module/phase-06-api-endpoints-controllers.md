# Phase 6: API Endpoints & Controllers

## Context

- Priority: High
- Status: Pending
- Dependencies: Phase 1 (Database), Phase 2 (Notification Classes)

## Overview

Create API endpoints for listing, reading, and managing notifications.

## Key Insights

- Mix of Inertia (web) and JSON (API) responses
- Notification center uses API for polling
- Settings page uses Inertia for preferences
- Pagination required for large notification lists

## Requirements

### Functional
- List notifications (paginated, filterable)
- Get unread count
- Mark single notification as read
- Mark all as read
- Delete notification
- Admin: send notifications to users

### Non-functional
- Efficient queries with indexes
- Rate limiting on polling endpoints
- Consistent error responses

## Architecture

### Endpoints

```
GET    /api/notifications              # List notifications (paginated)
GET    /api/notifications/unread-count # Get unread count only
POST   /api/notifications/{id}/read    # Mark as read
POST   /api/notifications/read-all     # Mark all as read
DELETE /api/notifications/{id}         # Delete notification

# Admin endpoints
POST   /api/admin/notifications/send   # Send notification to users
```

## Related Code Files

### Create
| File | Action | Description |
|------|--------|-------------|
| `app/Http/Controllers/Api/NotificationController.php` | Create | API controller |
| `app/Http/Controllers/Admin/NotificationController.php` | Create | Admin controller |
| `app/Http/Requests/ListNotificationsRequest.php` | Create | List validation |
| `app/Http/Requests/SendNotificationRequest.php` | Create | Send validation |
| `app/Http/Resources/NotificationResource.php` | Create | API resource |
| `routes/api.php` | Modify | Add API routes |

## Implementation Steps

### Step 1: Create API Resource

```php
// app/Http/Resources/NotificationResource.php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => class_basename($this->type),
            'data' => $this->data,
            'read_at' => $this->read_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
```

### Step 2: Create List Request

```php
// app/Http/Requests/ListNotificationsRequest.php
namespace App\Http\Requests;

use App\Enums\NotificationCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListNotificationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category' => ['nullable', Rule::enum(NotificationCategory::class)],
            'read' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
```

### Step 3: Create API Controller

```php
// app/Http/Controllers/Api/NotificationController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListNotificationsRequest;
use App\Http\Resources\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(ListNotificationsRequest $request): JsonResponse
    {
        $user = $request->user();
        $query = $user->notifications();

        // Filter by category
        if ($category = $request->validated('category')) {
            $query->where('data->category', $category);
        }

        // Filter by read status
        if ($request->has('read')) {
            $read = $request->boolean('read');
            $query->when($read, fn($q) => $q->whereNotNull('read_at'))
                  ->when(!$read, fn($q) => $q->whereNull('read_at'));
        }

        $perPage = $request->validated('per_page', 20);
        $notifications = $query->latest()->paginate($perPage);

        return response()->json([
            'data' => NotificationResource::collection($notifications->items()),
            'unread_count' => $user->unreadNotifications()->count(),
            'current_page' => $notifications->currentPage(),
            'last_page' => $notifications->lastPage(),
            'per_page' => $notifications->perPage(),
            'total' => $notifications->total(),
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $count = $request->user()->unreadNotifications()->count();

        return response()->json(['count' => $count]);
    }

    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()
            ->notifications()
            ->findOrFail($id);

        $notification->markAsRead();

        return response()->json([
            'message' => 'Notification marked as read',
            'notification' => new NotificationResource($notification),
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json([
            'message' => 'All notifications marked as read',
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()
            ->notifications()
            ->findOrFail($id);

        $notification->delete();

        return response()->json([
            'message' => 'Notification deleted',
        ]);
    }
}
```

### Step 4: Create Admin Controller

```php
// app/Http/Controllers/Admin/NotificationController.php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendNotificationRequest;
use App\Models\User;
use App\Notifications\Marketing\NewFeatureNotification;
use App\Notifications\System\MaintenanceNotification;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    public function index(): Response
    {
        return Inertia::render('admin/notifications/index', [
            'notification_types' => [
                'new_feature' => 'New Feature Announcement',
                'maintenance' => 'Scheduled Maintenance',
                'custom' => 'Custom Message',
            ],
        ]);
    }

    public function send(SendNotificationRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $notification = $this->buildNotification($validated);

        if ($validated['recipients'] === 'all') {
            $this->notificationService->sendToAll($notification);
        } else {
            $users = User::whereIn('id', $validated['user_ids'])->get();
            $this->notificationService->send($users, $notification);
        }

        return response()->json([
            'message' => 'Notification sent successfully',
        ]);
    }

    protected function buildNotification(array $data): object
    {
        return match ($data['type']) {
            'new_feature' => new NewFeatureNotification(
                $data['title'],
                $data['message'],
                $data['action_url'] ?? null
            ),
            'maintenance' => new MaintenanceNotification(
                $data['title'],
                $data['message'],
                $data['scheduled_at'] ?? null
            ),
            default => throw new \InvalidArgumentException('Unknown notification type'),
        };
    }
}
```

### Step 5: Create Send Request

```php
// app/Http/Requests/SendNotificationRequest.php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('notifications.send');
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['new_feature', 'maintenance', 'custom'])],
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:1000'],
            'action_url' => ['nullable', 'url'],
            'recipients' => ['required', Rule::in(['all', 'selected'])],
            'user_ids' => ['required_if:recipients,selected', 'array'],
            'user_ids.*' => ['exists:users,id'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
```

### Step 6: Add API Routes

```php
// routes/api.php
use App\Http\Controllers\Api\NotificationController;

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/{id}', [NotificationController::class, 'destroy']);
    });
});
```

### Step 7: Add Dashboard Routes

```php
// routes/dashboard.php (add to existing file)
use App\Http\Controllers\NotificationPreferenceController;
use App\Http\Controllers\Admin\NotificationController as AdminNotificationController;

// Notification Center Page
Route::get('/notifications', function () {
    return Inertia::render('notifications/index');
})->name('notifications');

// Notification Preferences (in settings group)
Route::prefix('settings/notifications')->group(function () {
    Route::get('/', [NotificationPreferenceController::class, 'index'])
        ->name('settings.notifications');
    Route::put('/preferences', [NotificationPreferenceController::class, 'update'])
        ->name('settings.notifications.update');
});

// Admin Notification Management
Route::prefix('admin/notifications')->middleware('can:notifications.manage')->group(function () {
    Route::get('/', [AdminNotificationController::class, 'index'])
        ->name('admin.notifications');
    Route::post('/send', [AdminNotificationController::class, 'send'])
        ->name('admin.notifications.send');
});
```

### Step 8: Add Permissions

```php
// database/seeders/PermissionSeeder.php (add to existing)
$notifications = [
    'notifications.view',
    'notifications.manage',
    'notifications.send',
];

foreach ($notifications as $permission) {
    Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
}
```

## Todo List

- [ ] Create NotificationResource
- [ ] Create ListNotificationsRequest
- [ ] Create NotificationController (API)
- [ ] Create AdminNotificationController
- [ ] Create SendNotificationRequest
- [ ] Add routes to api.php
- [ ] Add routes to dashboard.php
- [ ] Add notification permissions
- [ ] Run permission seeder
- [ ] Test all endpoints
- [ ] Add rate limiting

## Success Criteria

- [ ] List endpoint returns paginated notifications
- [ ] Category filtering works
- [ ] Unread count endpoint is fast
- [ ] Mark as read updates timestamp
- [ ] Mark all as read is efficient
- [ ] Delete removes notification
- [ ] Admin can send notifications
- [ ] Unauthorized users get 403

## Risk Assessment

| Risk | Impact | Mitigation |
|------|--------|------------|
| N+1 queries | Medium | Use eager loading |
| Large mark-all-read | High | Batch updates |
| Polling DDoS | Medium | Rate limiting |

## Security Considerations

- Validate notification belongs to user
- Rate limit polling endpoints (60 req/min)
- Authorize admin endpoints
- Sanitize notification content
- Validate recipient IDs for admin sends

## Next Steps

→ Phase 7: Testing & Validation
