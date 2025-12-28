<?php

namespace Modules\Ecommerce\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Ecommerce\Models\ProductTag;

class ProductTagController extends Controller
{
    /**
     * Display a listing of product tags.
     */
    public function index(Request $request): Response|JsonResponse
    {
        $this->authorize('viewAny', ProductTag::class);

        $tags = ProductTag::withCount(['products', 'activeProducts'])
            ->orderBy('name')
            ->get();

        if ($request->expectsJson()) {
            return response()->json([
                'tags' => $tags,
            ]);
        }

        return Inertia::render('Ecommerce::tags', [
            'tags' => $tags,
        ]);
    }

    /**
     * Show the form for creating a new product tag.
     */
    public function create(): Response
    {
        $this->authorize('create', ProductTag::class);

        return Inertia::render('Ecommerce::create-tag');
    }

    /**
     * Store a newly created product tag.
     */
    public function store(Request $request)
    {
        $this->authorize('create', ProductTag::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:product_tags,slug'],
            'description' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'max:7'],
        ]);

        $tag = ProductTag::create($validated);

        return redirect()->route('dashboard.ecommerce.product-tags.index')
            ->with('success', 'Product tag created successfully');
    }

    /**
     * Display the specified product tag.
     */
    public function show(Request $request, ProductTag $productTag): Response|JsonResponse
    {
        $this->authorize('view', $productTag);

        $products = $productTag->activeProducts()
            ->with(['category', 'user'])
            ->latest('created_at')
            ->paginate(10);

        if ($request->expectsJson()) {
            return response()->json([
                'tag' => $productTag,
                'products' => $products,
            ]);
        }

        return Inertia::render('Ecommerce::tag', [
            'tag' => $productTag,
            'products' => $products,
        ]);
    }

    /**
     * Show the form for editing the specified product tag.
     */
    public function edit(ProductTag $productTag): Response
    {
        $this->authorize('update', $productTag);

        return Inertia::render('Ecommerce::edit-tag', [
            'tag' => $productTag,
        ]);
    }

    /**
     * Update the specified product tag.
     */
    public function update(Request $request, ProductTag $productTag)
    {
        $this->authorize('update', $productTag);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:product_tags,slug,'.$productTag->id],
            'description' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'max:7'],
        ]);

        $productTag->update($validated);

        return redirect()->route('dashboard.ecommerce.product-tags.index')
            ->with('success', 'Product tag updated successfully');
    }

    /**
     * Remove the specified product tag.
     */
    public function destroy(ProductTag $productTag)
    {
        $this->authorize('delete', $productTag);

        $productTag->delete();

        return redirect()->route('dashboard.ecommerce.product-tags.index')
            ->with('success', 'Product tag deleted successfully');
    }
}
