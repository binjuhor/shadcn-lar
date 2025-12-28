<?php

namespace Modules\Ecommerce\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Ecommerce\Models\ProductCategory;

class ProductCategoryController extends Controller
{
    /**
     * Display a listing of product categories.
     */
    public function index(Request $request): Response|JsonResponse
    {
        $this->authorize('viewAny', ProductCategory::class);

        $categories = ProductCategory::withCount(['products', 'activeProducts'])
            ->with('parent')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        if ($request->expectsJson()) {
            return response()->json([
                'categories' => $categories,
            ]);
        }

        return Inertia::render('Ecommerce::categories', [
            'categories' => $categories,
        ]);
    }

    /**
     * Show the form for creating a new product category.
     */
    public function create(): Response
    {
        $this->authorize('create', ProductCategory::class);

        $categories = ProductCategory::active()
            ->whereNull('parent_id')
            ->orWhereNotNull('parent_id')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'parent_id']);

        return Inertia::render('Ecommerce::create-category', [
            'categories' => $categories,
        ]);
    }

    /**
     * Store a newly created product category.
     */
    public function store(Request $request)
    {
        $this->authorize('create', ProductCategory::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:product_categories,slug'],
            'description' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'max:7'],
            'icon' => ['nullable', 'string', 'max:255'],
            'parent_id' => ['nullable', 'exists:product_categories,id'],
            'is_active' => ['sometimes', 'boolean'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
        ]);

        $category = ProductCategory::create($validated);

        return redirect()->route('dashboard.ecommerce.product-categories.index')
            ->with('success', 'Product category created successfully');
    }

    /**
     * Display the specified product category.
     */
    public function show(Request $request, ProductCategory $productCategory): Response|JsonResponse
    {
        $this->authorize('view', $productCategory);

        $productCategory->load(['parent', 'children']);

        $products = $productCategory->activeProducts()
            ->with(['tags', 'user'])
            ->latest('created_at')
            ->paginate(10);

        if ($request->expectsJson()) {
            return response()->json([
                'category' => $productCategory,
                'products' => $products,
            ]);
        }

        return Inertia::render('Ecommerce::category', [
            'category' => $productCategory,
            'products' => $products,
        ]);
    }

    /**
     * Show the form for editing the specified product category.
     */
    public function edit(ProductCategory $productCategory): Response
    {
        $this->authorize('update', $productCategory);

        $productCategory->load('parent');

        $categories = ProductCategory::active()
            ->where('id', '!=', $productCategory->id)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'parent_id']);

        return Inertia::render('Ecommerce::edit-category', [
            'category' => $productCategory,
            'categories' => $categories,
        ]);
    }

    /**
     * Update the specified product category.
     */
    public function update(Request $request, ProductCategory $productCategory)
    {
        $this->authorize('update', $productCategory);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:product_categories,slug,'.$productCategory->id],
            'description' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'max:7'],
            'icon' => ['nullable', 'string', 'max:255'],
            'parent_id' => ['nullable', 'exists:product_categories,id'],
            'is_active' => ['sometimes', 'boolean'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
        ]);

        if (isset($validated['parent_id']) && $validated['parent_id'] === $productCategory->id) {
            return back()->withErrors(['parent_id' => 'A category cannot be its own parent']);
        }

        $productCategory->update($validated);

        return redirect()->route('dashboard.ecommerce.product-categories.index')
            ->with('success', 'Product category updated successfully');
    }

    /**
     * Remove the specified product category.
     */
    public function destroy(ProductCategory $productCategory)
    {
        $this->authorize('delete', $productCategory);

        if ($productCategory->products()->count() > 0) {
            return back()->withErrors(['error' => 'Cannot delete category with existing products']);
        }

        if ($productCategory->children()->count() > 0) {
            return back()->withErrors(['error' => 'Cannot delete category with child categories']);
        }

        $productCategory->delete();

        return redirect()->route('dashboard.ecommerce.product-categories.index')
            ->with('success', 'Product category deleted successfully');
    }
}
