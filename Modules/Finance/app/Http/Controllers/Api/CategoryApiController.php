<?php

namespace Modules\Finance\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Finance\Http\Resources\CategoryResource;
use Modules\Finance\Models\Category;

class CategoryApiController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $userId = auth()->id();

        $query = Category::userCategories($userId);

        if ($request->boolean('active_only', true)) {
            $query->where('is_active', true);
        }

        if ($request->has('type')) {
            $query->byType($request->type);
        }

        if ($request->boolean('tree', false)) {
            $categories = $query->orderBy('name')->get()->toTree();
        } else {
            $categories = $query->orderBy('name')->get();
        }

        return CategoryResource::collection($categories);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:income,expense,both'],
            'icon' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:7'],
            'parent_id' => ['nullable', 'exists:finance_categories,id'],
            'is_active' => ['boolean'],
            'is_passive' => ['boolean'],
        ]);

        $category = Category::create([
            'user_id' => auth()->id(),
            'name' => $validated['name'],
            'type' => $validated['type'],
            'icon' => $validated['icon'] ?? null,
            'color' => $validated['color'] ?? null,
            'parent_id' => $validated['parent_id'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'is_passive' => $validated['is_passive'] ?? false,
        ]);

        return response()->json([
            'message' => 'Category created successfully',
            'data' => new CategoryResource($category),
        ], 201);
    }

    public function show(Category $category): JsonResponse
    {
        $userId = auth()->id();

        if ($category->user_id !== null && $category->user_id !== $userId) {
            abort(403, 'Unauthorized');
        }

        $category->load('parent', 'children');

        return response()->json([
            'data' => new CategoryResource($category),
        ]);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $userId = auth()->id();

        if ($category->user_id !== $userId) {
            abort(403, 'Cannot update system categories');
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'in:income,expense,both'],
            'icon' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:7'],
            'parent_id' => ['nullable', 'exists:finance_categories,id'],
            'is_active' => ['sometimes', 'boolean'],
            'is_passive' => ['sometimes', 'boolean'],
        ]);

        $category->update($validated);

        return response()->json([
            'message' => 'Category updated successfully',
            'data' => new CategoryResource($category),
        ]);
    }

    public function destroy(Category $category): JsonResponse
    {
        $userId = auth()->id();

        if ($category->user_id !== $userId) {
            abort(403, 'Cannot delete system categories');
        }

        if ($category->transactions()->exists()) {
            return response()->json([
                'message' => 'Cannot delete category with existing transactions',
            ], 422);
        }

        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully',
        ]);
    }
}
