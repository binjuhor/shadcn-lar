<?php

namespace Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Finance\Models\Budget;
use Modules\Finance\Models\Category;
use Modules\Finance\Models\Currency;
use Modules\Finance\Services\BudgetService;

class BudgetController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private BudgetService $budgetService
    ) {}

    public function index(): Response
    {
        $userId = auth()->id();

        $budgets = Budget::with(['category', 'currency'])
            ->where('user_id', $userId)
            ->orderBy('start_date', 'desc')
            ->get();

        $categories = Category::userCategories($userId)
            ->where('type', 'expense')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $currencies = Currency::orderBy('code')->get();

        return Inertia::render('Finance::budgets/index', [
            'budgets' => $budgets,
            'categories' => $categories,
            'currencies' => $currencies,
        ]);
    }

    public function create(): Response
    {
        $userId = auth()->id();

        $categories = Category::userCategories($userId)
            ->where('type', 'expense')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $currencies = Currency::orderBy('code')->get();

        return Inertia::render('Finance::budgets/create', [
            'categories' => $categories,
            'currencies' => $currencies,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'exists:finance_categories,id'],
            'amount' => ['required', 'integer', 'min:1'],
            'currency_code' => ['required', 'string', 'size:3', 'exists:currencies,code'],
            'period_type' => ['required', 'in:weekly,monthly,quarterly,yearly,custom'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'is_active' => ['boolean'],
            'rollover' => ['boolean'],
        ]);

        $this->budgetService->createBudget([
            ...$validated,
            'user_id' => auth()->id(),
        ]);

        return Redirect::route('dashboard.finance.budgets.index')
            ->with('success', 'Budget created successfully');
    }

    public function edit(Budget $budget): Response
    {
        $this->authorize('update', $budget);

        $userId = auth()->id();

        $categories = Category::userCategories($userId)
            ->where('type', 'expense')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $currencies = Currency::orderBy('code')->get();

        return Inertia::render('Finance::budgets/edit', [
            'budget' => $budget,
            'categories' => $categories,
            'currencies' => $currencies,
        ]);
    }

    public function update(Request $request, Budget $budget): RedirectResponse
    {
        $this->authorize('update', $budget);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'category_id' => ['nullable', 'exists:finance_categories,id'],
            'amount' => ['sometimes', 'integer', 'min:1'],
            'currency_code' => ['sometimes', 'string', 'size:3', 'exists:currencies,code'],
            'period_type' => ['sometimes', 'in:weekly,monthly,quarterly,yearly,custom'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'after:start_date'],
            'is_active' => ['sometimes', 'boolean'],
            'rollover' => ['sometimes', 'boolean'],
        ]);

        $budget->update($validated);

        return Redirect::back()->with('success', 'Budget updated successfully');
    }

    public function destroy(Budget $budget): RedirectResponse
    {
        $this->authorize('delete', $budget);

        $budget->delete();

        return Redirect::route('dashboard.finance.budgets.index')
            ->with('success', 'Budget deleted successfully');
    }

    public function refresh(Budget $budget): RedirectResponse
    {
        $this->authorize('update', $budget);

        $this->budgetService->trackSpending($budget);

        return Redirect::back()->with('success', 'Budget spending updated');
    }
}
