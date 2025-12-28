<?php

namespace Modules\Notification\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Notification\Enums\NotificationCategory;
use Modules\Notification\Enums\NotificationChannel;
use Modules\Notification\Http\Resources\NotificationTemplateResource;
use Modules\Notification\Models\NotificationTemplate;
use Modules\Notification\Services\NotificationService;
use Spatie\Permission\Models\Role;

class AdminNotificationController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    public function send(): Response
    {
        $this->authorize('send', NotificationTemplate::class);

        return Inertia::render('Notification::send/index', [
            'templates' => NotificationTemplateResource::collection(
                NotificationTemplate::active()->get()
            )->resolve(),
            'categories' => collect(NotificationCategory::cases())->map(fn ($c) => [
                'value' => $c->value,
                'label' => $c->label(),
                'icon' => $c->icon(),
            ])->values()->all(),
            'channels' => collect(NotificationChannel::cases())->map(fn ($c) => [
                'value' => $c->value,
                'label' => $c->label(),
                'icon' => $c->icon(),
            ])->values()->all(),
            'roles' => Role::all(['id', 'name'])->map(fn ($r) => [
                'value' => $r->id,
                'label' => ucfirst($r->name),
            ])->values()->all(),
        ]);
    }

    public function sendNotification(Request $request): JsonResponse
    {
        $this->authorize('send', NotificationTemplate::class);

        $validated = $request->validate([
            'recipient_type' => 'required|in:users,roles,all',
            'user_ids' => 'required_if:recipient_type,users|array',
            'user_ids.*' => 'exists:users,id',
            'role_ids' => 'required_if:recipient_type,roles|array',
            'role_ids.*' => 'exists:roles,id',
            'use_template' => 'boolean',
            'template_id' => 'required_if:use_template,true|nullable|exists:notification_templates,id',
            'template_variables' => 'nullable|array',
            'title' => 'required_if:use_template,false|nullable|string|max:255',
            'message' => 'required_if:use_template,false|nullable|string',
            'category' => 'required_if:use_template,false|nullable|string|in:'.implode(',', array_column(NotificationCategory::cases(), 'value')),
            'channels' => 'required_if:use_template,false|nullable|array|min:1',
            'channels.*' => 'string|in:'.implode(',', array_column(NotificationChannel::cases(), 'value')),
            'action_url' => 'nullable|url',
            'action_label' => 'nullable|string|max:50',
        ]);

        try {
            $recipients = $this->getRecipients($validated);

            if ($recipients->isEmpty()) {
                return response()->json([
                    'message' => 'No recipients found.',
                ], 422);
            }

            if ($validated['use_template'] ?? false) {
                $template = NotificationTemplate::findOrFail($validated['template_id']);

                $this->notificationService->sendFromTemplate(
                    template: $template,
                    recipients: $recipients,
                    variables: $validated['template_variables'] ?? [],
                    actionUrl: $validated['action_url'] ?? null,
                    actionLabel: $validated['action_label'] ?? null
                );
            } else {
                $channels = array_map(
                    fn ($c) => NotificationChannel::from($c),
                    $validated['channels']
                );

                $this->notificationService->sendToUsers(
                    users: $recipients,
                    title: $validated['title'],
                    message: $validated['message'],
                    category: NotificationCategory::from($validated['category']),
                    channels: $channels,
                    actionUrl: $validated['action_url'] ?? null,
                    actionLabel: $validated['action_label'] ?? null
                );
            }

            return response()->json([
                'message' => "Notification sent to {$recipients->count()} user(s).",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => "Failed to send notification: {$e->getMessage()}",
            ], 500);
        }
    }

    public function searchUsers(Request $request): JsonResponse
    {
        $search = $request->get('q', '');

        $users = User::query()
            ->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            })
            ->limit(20)
            ->get(['id', 'name', 'email']);

        return response()->json([
            'users' => $users->map(fn ($u) => [
                'value' => $u->id,
                'label' => $u->name,
                'description' => $u->email,
            ])->values()->all(),
        ]);
    }

    protected function getRecipients(array $validated)
    {
        return match ($validated['recipient_type']) {
            'users' => User::whereIn('id', $validated['user_ids'])->get(),
            'roles' => User::whereHas('roles', fn ($q) => $q->whereIn('id', $validated['role_ids']))->get(),
            'all' => User::all(),
            default => collect(),
        };
    }
}
