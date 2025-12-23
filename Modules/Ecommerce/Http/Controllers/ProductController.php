<?php

namespace Modules\Ecommerce\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Modules\Ecommerce\Models\Product;
use Modules\Ecommerce\Models\ProductCategory;
use Modules\Ecommerce\Models\ProductTag;
use Modules\Ecommerce\Http\Resources\ProductResource;
use Modules\Ecommerce\Http\Resources\ProductCategoryResource;
use Modules\Ecommerce\Http\Resources\ProductTagResource;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    /**
     * Display a listing of products.
     */
    public function index(Request $request): Response|JsonResponse
    {
        $this->authorize('viewAny', Product::class);

        $query = Product::with(['category', 'tags', 'user']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('description', 'like', '%' . $request->search . '%')
                    ->orWhere('sku', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->boolean('featured')) {
            $query->where('is_featured', true);
        }

        $products = $query->latest()->paginate(15)->withQueryString();

        if ($request->expectsJson()) {
            return response()->json([
                'products' => ProductResource::collection($products),
            ]);
        }

        return Inertia::render('ecommerce/products', [
            'products' => [
                'data' => ProductResource::collection($products->items())->resolve(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
            'filters' => $request->only(['search', 'status', 'category_id', 'featured']),
            'categories' => ProductCategoryResource::collection(ProductCategory::active()->get(['id', 'name', 'slug', 'is_active']))->resolve(),
            'tags' => ProductTagResource::collection(ProductTag::withCount('products')->orderBy('name')->get(['id', 'name', 'slug']))->resolve(),
        ]);
    }

    /**
     * Show the form for creating a new product.
     */
    public function create(): Response
    {
        $this->authorize('create', Product::class);

        return Inertia::render('ecommerce/create-product', [
            'categories' => ProductCategoryResource::collection(ProductCategory::active()->orderBy('name')->get(['id', 'name', 'slug', 'is_active']))->resolve(),
            'tags' => ProductTagResource::collection(ProductTag::orderBy('name')->get(['id', 'name', 'slug']))->resolve(),
        ]);
    }

    /**
     * Store a newly created product.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Product::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:products,slug'],
            'description' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],
            'sku' => ['nullable', 'string', 'max:255', 'unique:products,sku'],
            'price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'track_inventory' => ['sometimes', 'boolean'],
            'status' => ['required', 'in:draft,active,archived'],
            'featured_image' => ['nullable'],
            'images' => ['nullable', 'array'],
            'category_id' => ['nullable', 'exists:product_categories,id'],
            'is_featured' => ['sometimes', 'boolean'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['exists:product_tags,id'],
        ]);

        $validated['user_id'] = Auth::id();

        $tagIds = $validated['tag_ids'] ?? [];
        unset($validated['tag_ids']);

        // Handle file upload for featured image
        if ($request->hasFile('featured_image')) {
            $validated['featured_image'] = $request->file('featured_image')->store('products', 'public');
        }

        $product = Product::create($validated);

        if (! empty($tagIds)) {
            $product->tags()->sync($tagIds);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Product created successfully',
                'product' => $product->load(['category', 'tags', 'user']),
            ], 201);
        }

        return redirect()->route('dashboard.ecommerce.products.index')
            ->with('success', 'Product created successfully');
    }

    /**
     * Display the specified product.
     */
    public function show(Request $request, Product $product): Response|JsonResponse
    {
        $this->authorize('view', $product);

        $product->load(['category', 'tags', 'user']);
        $product->incrementViewsCount();

        if ($request->expectsJson()) {
            return response()->json([
                'product' => $product,
            ]);
        }

        return Inertia::render('ecommerce/product', [
            'product' => $product,
        ]);
    }

    /**
     * Show the form for editing the specified product.
     */
    public function edit(Product $product): Response
    {
        $this->authorize('update', $product);

        $product->load(['category', 'tags', 'user']);

        return Inertia::render('ecommerce/edit-product', [
            'product' => ProductResource::make($product)->resolve(),
            'categories' => ProductCategoryResource::collection(ProductCategory::active()->orderBy('name')->get(['id', 'name', 'slug', 'is_active']))->resolve(),
            'tags' => ProductTagResource::collection(ProductTag::orderBy('name')->get(['id', 'name', 'slug']))->resolve(),
        ]);
    }

    /**
     * Update the specified product.
     */
    public function update(Request $request, Product $product)
    {
        $this->authorize('update', $product);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:products,slug,' . $product->id],
            'description' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],
            'sku' => ['nullable', 'string', 'max:255', 'unique:products,sku,' . $product->id],
            'price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'track_inventory' => ['sometimes', 'boolean'],
            'status' => ['required', 'in:draft,active,archived'],
            'featured_image' => ['nullable'],
            'remove_featured_image' => ['nullable', 'boolean'],
            'images' => ['nullable', 'array'],
            'category_id' => ['nullable', 'exists:product_categories,id'],
            'is_featured' => ['sometimes', 'boolean'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['exists:product_tags,id'],
        ]);

        $tagIds = $validated['tag_ids'] ?? [];
        unset($validated['tag_ids']);

        // Handle file upload for featured image
        if ($request->hasFile('featured_image')) {
            $validated['featured_image'] = $request->file('featured_image')->store('products', 'public');
        } elseif ($request->boolean('remove_featured_image')) {
            $validated['featured_image'] = null;
        } else {
            unset($validated['featured_image']);
        }
        unset($validated['remove_featured_image']);

        $product->update($validated);

        if (isset($tagIds)) {
            $product->tags()->sync($tagIds);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Product updated successfully',
                'product' => $product->fresh(['category', 'tags', 'user']),
            ]);
        }

        return redirect()->route('dashboard.ecommerce.products.index')
            ->with('success', 'Product updated successfully');
    }

    /**
     * Remove the specified product.
     */
    public function destroy(Request $request, Product $product)
    {
        $this->authorize('delete', $product);

        if ($product->orderItems()->count() > 0) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Cannot delete product with existing orders'], 422);
            }
            return back()->withErrors(['error' => 'Cannot delete product with existing orders']);
        }

        $product->delete();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Product deleted successfully']);
        }

        return redirect()->route('dashboard.ecommerce.products.index')
            ->with('success', 'Product deleted successfully');
    }
}