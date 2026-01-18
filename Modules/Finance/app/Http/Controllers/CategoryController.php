<?php

namespace Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Redirect;
use Inertia\{Inertia, Response};
use Modules\Finance\Models\Category;

class CategoryController extends Controller
{
    public function index(): Response
    {
        $categories = Category::userCategories(auth()->id())
            ->with('children')
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        return Inertia::render('Finance::categories/index', [
            'categories' => $categories,
        ]);
    }

    public function create(): Response
    {
        $parentCategories = Category::userCategories(auth()->id())
            ->whereNull('parent_id')
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        return Inertia::render('Finance::categories/create', [
            'parentCategories' => $parentCategories,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', 'in:income,expense'],
            'icon' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:7'],
            'parent_id' => ['nullable', 'exists:finance_categories,id'],
            'is_active' => ['boolean'],
            'is_passive' => ['boolean'],
        ]);

        Category::create([
            'user_id' => auth()->id(),
            'name' => $validated['name'],
            'type' => $validated['type'],
            'icon' => $validated['icon'] ?? null,
            'color' => $validated['color'] ?? null,
            'parent_id' => $validated['parent_id'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'is_passive' => $validated['is_passive'] ?? false,
        ]);

        return Redirect::route('dashboard.finance.categories.index')
            ->with('success', 'Category created successfully');
    }

    public function edit(Category $category): Response
    {
        if ($category->user_id !== null && $category->user_id !== auth()->id()) {
            abort(403);
        }

        $parentCategories = Category::userCategories(auth()->id())
            ->whereNull('parent_id')
            ->where('id', '!=', $category->id)
            ->where('type', $category->type)
            ->orderBy('name')
            ->get();

        return Inertia::render('Finance::categories/edit', [
            'category' => $category,
            'parentCategories' => $parentCategories,
        ]);
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        if ($category->user_id !== null && $category->user_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'type' => ['sometimes', 'in:income,expense'],
            'icon' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:7'],
            'parent_id' => ['nullable', 'exists:finance_categories,id'],
            'is_active' => ['sometimes', 'boolean'],
            'is_passive' => ['sometimes', 'boolean'],
        ]);

        $category->update($validated);

        return Redirect::back()->with('success', 'Category updated successfully');
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->user_id !== auth()->id()) {
            abort(403);
        }

        if ($category->transactions()->exists()) {
            return Redirect::back()
                ->withErrors(['error' => 'Cannot delete category with existing transactions']);
        }

        if ($category->children()->exists()) {
            return Redirect::back()
                ->withErrors(['error' => 'Cannot delete category with subcategories. Delete subcategories first.']);
        }

        $category->delete();

        return Redirect::route('dashboard.finance.categories.index')
            ->with('success', 'Category deleted successfully');
    }
}
