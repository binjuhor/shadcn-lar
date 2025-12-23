<?php

namespace Modules\Blog\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Blog\Models\Post;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\Tag;
use Modules\Blog\Http\Resources\PostResource;
use Modules\Blog\Http\Resources\CategoryResource;
use Modules\Blog\Http\Resources\TagResource;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    /**
     * Display a listing of posts.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Post::class);
        $query = Post::with(['category', 'tags', 'user'])
            ->latest('created_at');

        // Apply filters
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'like', '%' . $request->search . '%')
                  ->orWhere('content', 'like', '%' . $request->search . '%')
                  ->orWhere('excerpt', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('category')) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('categories.slug', $request->category)
                  ->orWhere('categories.id', $request->category);
            });
        }

        if ($request->filled('tag')) {
            $query->whereHas('tags', function ($q) use ($request) {
                $q->where('tags.slug', $request->tag)
                  ->orWhere('tags.id', $request->tag);
            });
        }

        if ($request->boolean('featured')) {
            $query->where('is_featured', true);
        }

        $posts = $query->paginate(15)->withQueryString();

        return Inertia::render('blog/posts', [
            'posts' => [
                'data' => PostResource::collection($posts->items())->resolve(),
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
            ],
            'filters' => $request->only(['search', 'status', 'category', 'tag', 'featured']),
            'categories' => CategoryResource::collection(Category::active()->get(['id', 'name', 'slug']))->resolve(),
            'tags' => TagResource::collection(Tag::active()->popular(20)->get(['id', 'name', 'slug']))->resolve(),
        ]);
    }

    /**
     * Show the form for creating a new post.
     */
    public function create(): Response
    {
        $this->authorize('create', Post::class);

        return Inertia::render('blog/create-post', [
            'categories' => CategoryResource::collection(Category::active()->orderBy('name')->get(['id', 'name', 'slug']))->resolve(),
            'tags' => TagResource::collection(Tag::active()->orderBy('name')->get(['id', 'name', 'slug']))->resolve(),
        ]);
    }

    /**
     * Store a newly created post.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Post::class);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:posts,slug',
            'content' => 'required|string',
            'excerpt' => 'nullable|string|max:500',
            'featured_image' => 'nullable|file|image|max:5120', // 5MB max
            'status' => 'required|in:draft,published,scheduled,archived',
            'published_at' => 'nullable|date',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'category_id' => 'nullable|exists:categories,id',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'exists:tags,id',
            'is_featured' => 'sometimes|boolean',
        ]);

        try {
            DB::beginTransaction();

            $validated['user_id'] = auth()->id();

            // Handle scheduled posts
            if ($validated['status'] === 'scheduled' && empty($validated['published_at'])) {
                throw ValidationException::withMessages([
                    'published_at' => 'Published date is required for scheduled posts.'
                ]);
            }

            // Set published_at for published posts
            if ($validated['status'] === 'published' && empty($validated['published_at'])) {
                $validated['published_at'] = now();
            }

            // Extract tag_ids and featured_image before creating post
            $tagIds = $validated['tag_ids'] ?? [];
            $featuredImage = $validated['featured_image'] ?? null;
            unset($validated['tag_ids'], $validated['featured_image']);

            $post = Post::create($validated);

            // Handle featured image upload
            if ($featuredImage) {
                $post->addMedia($featuredImage)
                    ->toMediaCollection('featured_image');
            }

            // Attach tags
            if (!empty($tagIds)) {
                $post->tags()->attach($tagIds);

                // Update tag usage counts
                Tag::whereIn('id', $tagIds)->get()->each(function ($tag) {
                    $tag->updateUsageCount();
                });
            }

            DB::commit();

            return redirect()->route('dashboard.posts.index')
                ->with('success', 'Post created successfully!');

        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            return back()
                ->withErrors(['error' => 'Failed to create post: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Display the specified post.
     */
    public function show(Post $post): Response
    {
        $this->authorize('view', $post);

        $post->load(['category', 'tags', 'user']);

        // Increment view count
        $post->increment('views_count');

        return Inertia::render('blog/post', [
            'post' => PostResource::make($post)->resolve(),
        ]);
    }

    /**
     * Show the form for editing the specified post.
     */
    public function edit(Post $post): Response
    {
        $this->authorize('update', $post);

        $post->load(['category', 'tags', 'user']);

        return Inertia::render('blog/edit-post', [
            'post' => PostResource::make($post)->resolve(),
            'categories' => CategoryResource::collection(Category::active()->orderBy('name')->get(['id', 'name', 'slug']))->resolve(),
            'tags' => TagResource::collection(Tag::active()->orderBy('name')->get(['id', 'name', 'slug']))->resolve(),
        ]);
    }

    /**
     * Update the specified post.
     */
    public function update(Request $request, Post $post)
    {
        $this->authorize('update', $post);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:posts,slug,' . $post->id,
            'content' => 'required|string',
            'excerpt' => 'nullable|string|max:500',
            'featured_image' => 'nullable|file|image|max:5120', // 5MB max
            'remove_featured_image' => 'nullable|boolean',
            'status' => 'required|in:draft,published,scheduled,archived',
            'published_at' => 'nullable|date',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'category_id' => 'nullable|exists:categories,id',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'exists:tags,id',
            'is_featured' => 'sometimes|boolean',
        ]);

        try {
            DB::beginTransaction();

            // Handle status changes
            if ($validated['status'] === 'scheduled' && empty($validated['published_at'])) {
                throw ValidationException::withMessages([
                    'published_at' => 'Published date is required for scheduled posts.'
                ]);
            }

            if ($validated['status'] === 'published' && !$post->published_at && empty($validated['published_at'])) {
                $validated['published_at'] = now();
            }

            // Clear published_at when changing to draft or archived
            if (in_array($validated['status'], ['draft', 'archived']) && empty($validated['published_at'])) {
                $validated['published_at'] = null;
            }

            // Extract tag_ids, featured_image, and remove_featured_image before updating post
            $oldTagIds = $post->tags->pluck('id')->toArray();
            $newTagIds = $validated['tag_ids'] ?? [];
            $featuredImage = $validated['featured_image'] ?? null;
            $removeFeaturedImage = $request->boolean('remove_featured_image');
            unset($validated['tag_ids'], $validated['featured_image'], $validated['remove_featured_image']);

            $post->update($validated);

            // Handle featured image upload or removal
            if ($featuredImage) {
                // Clear old media and add new
                $post->clearMediaCollection('featured_image');
                $post->addMedia($featuredImage)
                    ->toMediaCollection('featured_image');
            } elseif ($removeFeaturedImage) {
                // Remove existing featured image if requested
                $post->clearMediaCollection('featured_image');
            }

            // Sync tags
            $post->tags()->sync($newTagIds);

            DB::commit();

            // Update usage counts for affected tags (after commit)
            $affectedTagIds = array_unique(array_merge($oldTagIds, $newTagIds));
            Tag::whereIn('id', $affectedTagIds)->get()->each(function ($tag) {
                $tag->updateUsageCount();
            });

            return redirect()->route('dashboard.posts.index')
                ->with('success', 'Post updated successfully!');

        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            return back()
                ->withErrors(['error' => 'Failed to update post: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Remove the specified post.
     */
    public function destroy(Post $post)
    {
        $this->authorize('delete', $post);

        try {
            DB::beginTransaction();

            // Update tag usage counts before deletion
            $tagIds = $post->tags->pluck('id')->toArray();
            $post->tags()->detach();

            Tag::whereIn('id', $tagIds)->get()->each(function ($tag) {
                $tag->updateUsageCount();
            });

            $post->delete();

            DB::commit();

            return redirect()->route('dashboard.posts.index')
                ->with('success', 'Post deleted successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()
                ->withErrors(['error' => 'Failed to delete post: ' . $e->getMessage()]);
        }
    }
}
