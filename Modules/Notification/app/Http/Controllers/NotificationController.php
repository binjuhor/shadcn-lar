<?php

namespace Modules\Notification\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\{JsonResponse, Request};
use Inertia\{Inertia, Response};
use Modules\Notification\{Http\Resources\NotificationResource, Models\Notification};

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Notification::class);

        $query = Notification::forUser(auth()->id())
            ->latest();

        if ($request->filled('status')) {
            if ($request->status === 'unread') {
                $query->unread();
            } elseif ($request->status === 'read') {
                $query->read();
            }
        }

        if ($request->filled('category')) {
            $query->byCategory($request->category);
        }

        $notifications = $query->paginate(20)->withQueryString();

        return Inertia::render('Notification::index', [
            'notifications' => [
                'data' => NotificationResource::collection($notifications->items())->resolve(),
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
            'filters' => $request->only(['status', 'category']),
            'unread_count' => Notification::forUser(auth()->id())->unread()->count(),
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $notification = Notification::findOrFail($id);
        $this->authorize('view', $notification);

        return response()->json([
            'notification' => NotificationResource::make($notification)->resolve(),
        ]);
    }

    public function markAsRead(string $id): JsonResponse
    {
        $notification = Notification::findOrFail($id);
        $this->authorize('markAsRead', $notification);

        $notification->markAsRead();

        return response()->json([
            'message' => 'Notification marked as read.',
            'notification' => NotificationResource::make($notification)->resolve(),
        ]);
    }

    public function markAsUnread(string $id): JsonResponse
    {
        $notification = Notification::findOrFail($id);
        $this->authorize('update', $notification);

        $notification->update(['read_at' => null]);

        return response()->json([
            'message' => 'Notification marked as unread.',
            'notification' => NotificationResource::make($notification->fresh())->resolve(),
        ]);
    }

    public function markAllAsRead(): JsonResponse
    {
        $this->authorize('markAllAsRead', Notification::class);

        Notification::forUser(auth()->id())
            ->unread()
            ->update(['read_at' => now()]);

        return response()->json([
            'message' => 'All notifications marked as read.',
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $notification = Notification::findOrFail($id);
        $this->authorize('delete', $notification);

        $notification->delete();

        return response()->json([
            'message' => 'Notification deleted.',
        ]);
    }

    public function destroyMultiple(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|uuid',
        ]);

        $notifications = Notification::whereIn('id', $validated['ids'])
            ->forUser(auth()->id())
            ->get();

        foreach ($notifications as $notification) {
            $this->authorize('delete', $notification);
        }

        Notification::whereIn('id', $validated['ids'])
            ->forUser(auth()->id())
            ->delete();

        return response()->json([
            'message' => 'Notifications deleted.',
        ]);
    }

    public function unreadCount(): JsonResponse
    {
        $count = Notification::forUser(auth()->id())->unread()->count();

        return response()->json([
            'count' => $count,
        ]);
    }

    public function recent(): JsonResponse
    {
        $notifications = Notification::forUser(auth()->id())
            ->latest()
            ->take(5)
            ->get();

        return response()->json([
            'notifications' => NotificationResource::collection($notifications)->resolve(),
            'unread_count' => Notification::forUser(auth()->id())->unread()->count(),
        ]);
    }
}
