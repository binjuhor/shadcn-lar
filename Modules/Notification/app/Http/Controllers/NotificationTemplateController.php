<?php

namespace Modules\Notification\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\{Inertia, Response};
use Modules\Notification\{
    Enums\NotificationCategory,
    Enums\NotificationChannel,
    Http\Resources\NotificationTemplateResource,
    Models\NotificationTemplate
};

class NotificationTemplateController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', NotificationTemplate::class);

        $query = NotificationTemplate::query()->latest();

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                    ->orWhere('subject', 'like', "%{$request->search}%");
            });
        }

        if ($request->filled('category')) {
            $query->byCategory($request->category);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $templates = $query->paginate(15)->withQueryString();

        return Inertia::render('Notification::templates/index', [
            'templates' => [
                'data' => NotificationTemplateResource::collection($templates->items())->resolve(),
                'current_page' => $templates->currentPage(),
                'last_page' => $templates->lastPage(),
                'per_page' => $templates->perPage(),
                'total' => $templates->total(),
            ],
            'filters' => $request->only(['search', 'category', 'status']),
            'categories' => collect(NotificationCategory::cases())->map(fn ($c) => [
                'value' => $c->value,
                'label' => $c->label(),
            ])->values()->all(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', NotificationTemplate::class);

        return Inertia::render('Notification::templates/create', [
            'categories' => collect(NotificationCategory::cases())->map(fn ($c) => [
                'value' => $c->value,
                'label' => $c->label(),
                'description' => $c->description(),
            ])->values()->all(),
            'channels' => collect(NotificationChannel::cases())->map(fn ($c) => [
                'value' => $c->value,
                'label' => $c->label(),
                'description' => $c->description(),
            ])->values()->all(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', NotificationTemplate::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:notification_templates,slug',
            'subject' => 'required|string|max:500',
            'body' => 'required|string',
            'category' => 'required|string|in:'.implode(',', array_column(NotificationCategory::cases(), 'value')),
            'channels' => 'required|array|min:1',
            'channels.*' => 'string|in:'.implode(',', array_column(NotificationChannel::cases(), 'value')),
            'variables' => 'nullable|array',
            'variables.*' => 'string|max:50',
            'is_active' => 'boolean',
        ]);

        try {
            DB::beginTransaction();

            $template = NotificationTemplate::create($validated);

            DB::commit();

            return redirect()->route('dashboard.notifications.templates.index')
                ->with('success', 'Template created successfully!');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()
                ->withErrors(['error' => "Failed to create template: {$e->getMessage()}"])
                ->withInput();
        }
    }

    public function show(NotificationTemplate $template): Response
    {
        $this->authorize('view', $template);

        return Inertia::render('Notification::templates/show', [
            'template' => NotificationTemplateResource::make($template)->resolve(),
        ]);
    }

    public function edit(NotificationTemplate $template): Response
    {
        $this->authorize('update', $template);

        return Inertia::render('Notification::templates/edit', [
            'template' => NotificationTemplateResource::make($template)->resolve(),
            'categories' => collect(NotificationCategory::cases())->map(fn ($c) => [
                'value' => $c->value,
                'label' => $c->label(),
                'description' => $c->description(),
            ])->values()->all(),
            'channels' => collect(NotificationChannel::cases())->map(fn ($c) => [
                'value' => $c->value,
                'label' => $c->label(),
                'description' => $c->description(),
            ])->values()->all(),
        ]);
    }

    public function update(Request $request, NotificationTemplate $template)
    {
        $this->authorize('update', $template);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => "nullable|string|max:255|unique:notification_templates,slug,{$template->id}",
            'subject' => 'required|string|max:500',
            'body' => 'required|string',
            'category' => 'required|string|in:'.implode(',', array_column(NotificationCategory::cases(), 'value')),
            'channels' => 'required|array|min:1',
            'channels.*' => 'string|in:'.implode(',', array_column(NotificationChannel::cases(), 'value')),
            'variables' => 'nullable|array',
            'variables.*' => 'string|max:50',
            'is_active' => 'boolean',
        ]);

        try {
            DB::beginTransaction();

            $template->update($validated);

            DB::commit();

            return redirect()->route('dashboard.notifications.templates.index')
                ->with('success', 'Template updated successfully!');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()
                ->withErrors(['error' => "Failed to update template: {$e->getMessage()}"])
                ->withInput();
        }
    }

    public function destroy(NotificationTemplate $template)
    {
        $this->authorize('delete', $template);

        try {
            $template->delete();

            return redirect()->route('dashboard.notifications.templates.index')
                ->with('success', 'Template deleted successfully!');
        } catch (\Exception $e) {
            return back()
                ->withErrors(['error' => "Failed to delete template: {$e->getMessage()}"]);
        }
    }

    public function toggleStatus(NotificationTemplate $template)
    {
        $this->authorize('update', $template);

        $template->update(['is_active' => ! $template->is_active]);

        return response()->json([
            'message' => $template->is_active ? 'Template activated.' : 'Template deactivated.',
            'is_active' => $template->is_active,
        ]);
    }
}
