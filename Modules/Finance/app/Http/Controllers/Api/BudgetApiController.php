<?php

namespace Modules\Finance\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Finance\Http\Resources\BudgetResource;
use Modules\Finance\Models\Budget;
use Modules\Finance\Models\Transaction;
use Modules\Finance\Services\BudgetService;

class BudgetApiController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected BudgetService $budgetService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $userId = auth()->id();

        $this->budgetService->renewExpiredBudgets($userId);

        $query = Budget::with('category')
            ->where('user_id', $userId);

        if ($request->boolean('active_only', false)) {
            $query->where('is_active', true)
                ->where('end_date', '>=', now());
        }

        if ($request->has('period_type')) {
            $query->byPeriod($request->period_type);
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        $budgets = $query->orderBy('start_date', 'desc')->get();

        return BudgetResource::collection($budgets);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'exists:finance_categories,id'],
            'period_type' => ['required', 'in:weekly,monthly,yearly,custom'],
            'allocated_amount' => ['required', 'numeric', 'min:0.01'],
            'currency_code' => ['required', 'string', 'size:3', 'exists:currencies,code'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'is_active' => ['boolean'],
            'rollover' => ['boolean'],
        ]);

        $budget = Budget::create([
            'user_id' => auth()->id(),
            'name' => $validated['name'],
            'category_id' => $validated['category_id'] ?? null,
            'period_type' => $validated['period_type'],
            'allocated_amount' => $validated['allocated_amount'],
            'spent_amount' => 0,
            'currency_code' => $validated['currency_code'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'is_active' => $validated['is_active'] ?? true,
            'rollover' => $validated['rollover'] ?? false,
        ]);

        $budget->load('category');

        return response()->json([
            'message' => 'Budget created successfully',
            'data' => new BudgetResource($budget),
        ], 201);
    }

    public function show(Budget $budget): JsonResponse
    {
        $this->authorize('view', $budget);

        $budget->load('category');

        return response()->json([
            'data' => new BudgetResource($budget),
        ]);
    }

    public function update(Request $request, Budget $budget): JsonResponse
    {
        $this->authorize('update', $budget);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'category_id' => ['nullable', 'exists:finance_categories,id'],
            'period_type' => ['sometimes', 'in:weekly,monthly,yearly,custom'],
            'allocated_amount' => ['sometimes', 'numeric', 'min:0.01'],
            'currency_code' => ['sometimes', 'string', 'size:3', 'exists:currencies,code'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'after:start_date'],
            'is_active' => ['sometimes', 'boolean'],
            'rollover' => ['sometimes', 'boolean'],
        ]);

        $budget->update($validated);
        $budget->load('category');

        return response()->json([
            'message' => 'Budget updated successfully',
            'data' => new BudgetResource($budget),
        ]);
    }

    public function destroy(Budget $budget): JsonResponse
    {
        $this->authorize('delete', $budget);

        $budget->delete();

        return response()->json([
            'message' => 'Budget deleted successfully',
        ]);
    }

    public function refresh(Budget $budget): JsonResponse
    {
        $this->authorize('update', $budget);

        $spentAmount = Transaction::where('category_id', $budget->category_id)
            ->whereHas('account', fn ($q) => $q->where('user_id', auth()->id()))
            ->where('transaction_type', 'expense')
            ->whereBetween('transaction_date', [$budget->start_date, $budget->end_date])
            ->sum('amount');

        $budget->update(['spent_amount' => $spentAmount]);
        $budget->load('category');

        return response()->json([
            'message' => 'Budget spending refreshed',
            'data' => new BudgetResource($budget),
        ]);
    }

    public function summary(): JsonResponse
    {
        $userId = auth()->id();

        $activeBudgets = Budget::where('user_id', $userId)
            ->where('is_active', true)
            ->where('end_date', '>=', now())
            ->get();

        $totalAllocated = $activeBudgets->sum('allocated_amount');
        $totalSpent = $activeBudgets->sum('spent_amount');
        $overBudgetCount = $activeBudgets->filter(fn ($b) => $b->isOverBudget())->count();

        return response()->json([
            'data' => [
                'total_allocated' => (float) $totalAllocated,
                'total_spent' => (float) $totalSpent,
                'total_remaining' => (float) ($totalAllocated - $totalSpent),
                'active_budgets_count' => $activeBudgets->count(),
                'over_budget_count' => $overBudgetCount,
            ],
        ]);
    }
}
