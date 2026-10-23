<?php

namespace Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
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
        // System categories are shared, so they may only nest under other system categories
        $parentCategories = ($category->user_id === null ? Category::whereNull('user_id') : Category::userCategories(auth()->id()))
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
            'icon' => ['nullable', 'string', Rule::in(Category::ICONS)],
            'color' => ['nullable', 'string', 'max:7'],
            'parent_id' => ['nullable', 'exists:finance_categories,id'],
            'is_active' => ['boolean'],
            'is_passive' => ['boolean'],
            'is_loan' => ['boolean'],
            'expense_type' => ['nullable', 'in:essential,discretionary,savings'],
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
            'is_loan' => $validated['is_loan'] ?? false,
            'expense_type' => $validated['expense_type'] ?? null,
        ]);

        return Redirect::route('dashboard.finance.categories.index')
            ->with('success', 'Category created successfully');
    }

    public function edit(Category $category): Response
    {
        $this->authorizeEdit($category);

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
        $this->authorizeEdit($category);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'type' => ['sometimes', 'in:income,expense'],
            'icon' => ['nullable', 'string', Rule::in(Category::ICONS)],
            'color' => ['nullable', 'string', 'max:7'],
            'parent_id' => [
                'nullable',
                $category->user_id === null
                    ? Rule::exists('finance_categories', 'id')->whereNull('user_id')
                    : 'exists:finance_categories,id',
            ],
            'is_active' => ['sometimes', 'boolean'],
            'is_passive' => ['sometimes', 'boolean'],
            'is_loan' => ['sometimes', 'boolean'],
            'expense_type' => ['nullable', 'in:essential,discretionary,savings'],
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

    /**
     * Owners edit their own categories; shared system categories (no owner) are Super Admin only.
     */
    private function authorizeEdit(Category $category): void
    {
        if ($category->user_id === null) {
            abort_unless(auth()->user()->hasRole('Super Admin'), 403);

            return;
        }

        abort_if($category->user_id !== auth()->id(), 403);
    }
}
