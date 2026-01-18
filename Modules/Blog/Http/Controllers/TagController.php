<?php

namespace Modules\Blog\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\{JsonResponse, Request};
use Inertia\{Inertia, Response};
use Modules\Blog\Models\Tag;

class TagController extends Controller
{
    /**
     * Display a listing of tags.
     */
    public function index(Request $request): Response|JsonResponse
    {
        $this->authorize('viewAny', Tag::class);

        $tags = Tag::withCount(['posts', 'publishedPosts'])
            ->orderBy('usage_count', 'desc')
            ->orderBy('name')
            ->get();

        if ($request->expectsJson()) {
            return response()->json([
                'tags' => $tags,
            ]);
        }

        return Inertia::render('Blog::tags', [
            'tags' => $tags,
        ]);
    }

    /**
     * Show the form for creating a new tag.
     */
    public function create(): Response
    {
        $this->authorize('create', Tag::class);

        return Inertia::render('Blog::create-tag');
    }

    /**
     * Store a newly created tag.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Tag::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:tags,slug',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:7',
            'is_active' => 'sometimes|boolean',
        ]);

        $tag = Tag::create($validated);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Tag created successfully',
                'tag' => $tag,
            ], 201);
        }

        return redirect()->route('dashboard.tags.index')
            ->with('success', 'Tag created successfully');
    }

    /**
     * Display the specified tag with its posts.
     */
    public function show(Request $request, Tag $tag): Response|JsonResponse
    {
        $this->authorize('view', $tag);

        $posts = $tag->publishedPosts()
            ->with(['category', 'user'])
            ->latest('published_at')
            ->paginate(10);

        if ($request->expectsJson()) {
            return response()->json([
                'tag' => $tag,
                'posts' => $posts,
            ]);
        }

        return Inertia::render('Blog::tag', [
            'tag' => $tag,
            'posts' => $posts,
        ]);
    }

    /**
     * Show the form for editing the specified tag.
     */
    public function edit(Tag $tag): Response
    {
        $this->authorize('update', $tag);

        return Inertia::render('Blog::edit-tag', [
            'tag' => $tag,
        ]);
    }

    /**
     * Update the specified tag.
     */
    public function update(Request $request, Tag $tag)
    {
        $this->authorize('update', $tag);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:tags,slug,'.$tag->id,
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:7',
            'is_active' => 'sometimes|boolean',
        ]);

        $tag->update($validated);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Tag updated successfully',
                'tag' => $tag->fresh(),
            ]);
        }

        return redirect()->route('dashboard.tags.index')
            ->with('success', 'Tag updated successfully');
    }

    /**
     * Remove the specified tag.
     */
    public function destroy(Request $request, Tag $tag)
    {
        $this->authorize('delete', $tag);

        if ($tag->posts()->count() > 0) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Cannot delete tag with existing posts'], 422);
            }

            return back()->withErrors(['error' => 'Cannot delete tag with existing posts']);
        }

        $tag->delete();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Tag deleted successfully']);
        }

        return redirect()->route('dashboard.tags.index')
            ->with('success', 'Tag deleted successfully');
    }

    /**
     * Get popular tags for suggestions.
     */
    public function popular(): JsonResponse
    {
        $tags = Tag::active()
            ->popular(20)
            ->get(['id', 'name', 'slug', 'usage_count']);

        return response()->json([
            'tags' => $tags,
        ]);
    }
}
