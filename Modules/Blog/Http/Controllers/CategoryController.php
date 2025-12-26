<?php

namespace Modules\Blog\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Blog\Models\Category;

class CategoryController extends Controller
{
    /**
     * Display a listing of categories.
     */
    public function index(Request $request): Response|JsonResponse
    {
        $this->authorize('viewAny', Category::class);

        $categories = Category::withCount(['posts', 'publishedPosts'])
            ->with('parent')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        if ($request->expectsJson()) {
            return response()->json([
                'categories' => $categories,
            ]);
        }

        return Inertia::render('Blog::categories', [
            'categories' => $categories,
        ]);
    }

    /**
     * Show the form for creating a new category.
     */
    public function create(): Response
    {
        $this->authorize('create', Category::class);

        $categories = Category::active()
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'parent_id']);

        return Inertia::render('Blog::create-category', [
            'categories' => $categories,
        ]);
    }

    /**
     * Store a newly created category.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Category::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:categories,slug',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:7',
            'icon' => 'nullable|string|max:255',
            'parent_id' => 'nullable|exists:categories,id',
            'is_active' => 'sometimes|boolean',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
        ]);

        $category = Category::create($validated);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Category created successfully',
                'category' => $category->load('parent'),
            ], 201);
        }

        return redirect()->route('dashboard.categories.index')
            ->with('success', 'Category created successfully');
    }

    /**
     * Display the specified category with its posts.
     */
    public function show(Request $request, Category $category): Response|JsonResponse
    {
        $this->authorize('view', $category);

        $category->load(['parent', 'children']);

        $posts = $category->publishedPosts()
            ->with(['tags', 'user'])
            ->latest('published_at')
            ->paginate(10);

        if ($request->expectsJson()) {
            return response()->json([
                'category' => $category,
                'posts' => $posts,
            ]);
        }

        return Inertia::render('Blog::category', [
            'category' => $category,
            'posts' => $posts,
        ]);
    }

    /**
     * Show the form for editing the specified category.
     */
    public function edit(Category $category): Response
    {
        $this->authorize('update', $category);

        $category->load('parent');

        $categories = Category::active()
            ->where('id', '!=', $category->id)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'parent_id']);

        return Inertia::render('Blog::edit-category', [
            'category' => $category,
            'categories' => $categories,
        ]);
    }

    /**
     * Update the specified category.
     */
    public function update(Request $request, Category $category)
    {
        $this->authorize('update', $category);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:categories,slug,'.$category->id,
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:7',
            'icon' => 'nullable|string|max:255',
            'parent_id' => 'nullable|exists:categories,id',
            'is_active' => 'sometimes|boolean',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
        ]);

        if (isset($validated['parent_id']) && $validated['parent_id'] === $category->id) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'A category cannot be its own parent'], 422);
            }

            return back()->withErrors(['parent_id' => 'A category cannot be its own parent']);
        }

        $category->update($validated);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Category updated successfully',
                'category' => $category->fresh(['parent']),
            ]);
        }

        return redirect()->route('dashboard.categories.index')
            ->with('success', 'Category updated successfully');
    }

    /**
     * Remove the specified category.
     */
    public function destroy(Request $request, Category $category)
    {
        $this->authorize('delete', $category);

        if ($category->posts()->count() > 0) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Cannot delete category with existing posts'], 422);
            }

            return back()->withErrors(['error' => 'Cannot delete category with existing posts']);
        }

        if ($category->children()->count() > 0) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Cannot delete category with child categories'], 422);
            }

            return back()->withErrors(['error' => 'Cannot delete category with child categories']);
        }

        $category->delete();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Category deleted successfully']);
        }

        return redirect()->route('dashboard.categories.index')
            ->with('success', 'Category deleted successfully');
    }
}
