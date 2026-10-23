<?php

namespace Modules\Finance\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Modules\Finance\Http\Resources\FinancialPlanResource;
use Modules\Finance\Models\FinancialPlan;
use Modules\Finance\Services\PlanComparisonService;

class FinancialPlanApiController extends Controller
{
    public function __construct(
        protected PlanComparisonService $planComparison
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $userId = auth()->id();

        $plans = FinancialPlan::forUser($userId)
            ->with('periods')
            ->orderByDesc('created_at')
            ->get();

        return FinancialPlanResource::collection($plans);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'start_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'end_year' => ['required', 'integer', 'min:2000', 'max:2100', 'gte:start_year'],
            'currency_code' => ['required', 'string', 'size:3'],
            'status' => ['required', 'in:draft,active,archived'],
            'periods' => ['required', 'array', 'min:1'],
            'periods.*.year' => ['required', 'integer'],
            'periods.*.items' => ['nullable', 'array'],
            'periods.*.items.*.name' => ['required', 'string', 'max:255'],
            'periods.*.items.*.type' => ['required', 'in:income,expense'],
            'periods.*.items.*.planned_amount' => ['required', 'numeric', 'min:0'],
            'periods.*.items.*.recurrence' => ['required', 'in:one_time,monthly,quarterly,yearly'],
            'periods.*.items.*.category_id' => ['nullable', 'exists:finance_categories,id'],
            'periods.*.items.*.notes' => ['nullable', 'string'],
        ]);

        $plan = DB::transaction(function () use ($validated) {
            $plan = FinancialPlan::create([
                'user_id' => auth()->id(),
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'start_year' => $validated['start_year'],
                'end_year' => $validated['end_year'],
                'currency_code' => $validated['currency_code'],
                'status' => $validated['status'],
            ]);

            foreach ($validated['periods'] as $periodData) {
                $period = $plan->periods()->create([
                    'year' => $periodData['year'],
                    'planned_income' => 0,
                    'planned_expense' => 0,
                ]);

                if (! empty($periodData['items'])) {
                    foreach ($periodData['items'] as $itemData) {
                        $period->items()->create([
                            'name' => $itemData['name'],
                            'type' => $itemData['type'],
                            'planned_amount' => round((float) $itemData['planned_amount'], 2),
                            'recurrence' => $itemData['recurrence'],
                            'category_id' => $itemData['category_id'] ?? null,
                            'notes' => $itemData['notes'] ?? null,
                        ]);
                    }
                }

                $period->recalculateTotals();
            }

            return $plan;
        });

        $plan->load('periods.items.category');

        return response()->json([
            'message' => 'Financial plan created successfully',
            'data' => new FinancialPlanResource($plan),
        ], 201);
    }

    public function show(FinancialPlan $plan): JsonResponse
    {
        $this->authorizeAccess($plan);

        $plan->load('periods.items.category');

        return response()->json([
            'data' => new FinancialPlanResource($plan),
        ]);
    }

    public function update(Request $request, FinancialPlan $plan): JsonResponse
    {
        $this->authorizeAccess($plan);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:draft,active,archived'],
            'periods' => ['required', 'array', 'min:1'],
            'periods.*.id' => ['nullable', 'exists:finance_plan_periods,id'],
            'periods.*.year' => ['required', 'integer'],
            'periods.*.items' => ['nullable', 'array'],
            'periods.*.items.*.id' => ['nullable', 'exists:finance_plan_items,id'],
            'periods.*.items.*.name' => ['required', 'string', 'max:255'],
            'periods.*.items.*.type' => ['required', 'in:income,expense'],
            'periods.*.items.*.planned_amount' => ['required', 'numeric', 'min:0'],
            'periods.*.items.*.recurrence' => ['required', 'in:one_time,monthly,quarterly,yearly'],
            'periods.*.items.*.category_id' => ['nullable', 'exists:finance_categories,id'],
            'periods.*.items.*.notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($plan, $validated) {
            $plan->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'status' => $validated['status'],
            ]);

            $existingPeriodIds = [];
            foreach ($validated['periods'] as $periodData) {
                if (! empty($periodData['id'])) {
                    $period = $plan->periods()->findOrFail($periodData['id']);
                    $existingPeriodIds[] = $period->id;
                } else {
                    $period = $plan->periods()->create([
                        'year' => $periodData['year'],
                        'planned_income' => 0,
                        'planned_expense' => 0,
                    ]);
                    $existingPeriodIds[] = $period->id;
                }

                $existingItemIds = [];
                if (! empty($periodData['items'])) {
                    foreach ($periodData['items'] as $itemData) {
                        if (! empty($itemData['id'])) {
                            $item = $period->items()->findOrFail($itemData['id']);
                            $item->update([
                                'name' => $itemData['name'],
                                'type' => $itemData['type'],
                                'planned_amount' => round((float) $itemData['planned_amount'], 2),
                                'recurrence' => $itemData['recurrence'],
                                'category_id' => $itemData['category_id'] ?? null,
                                'notes' => $itemData['notes'] ?? null,
                            ]);
                            $existingItemIds[] = $item->id;
                        } else {
                            $newItem = $period->items()->create([
                                'name' => $itemData['name'],
                                'type' => $itemData['type'],
                                'planned_amount' => round((float) $itemData['planned_amount'], 2),
                                'recurrence' => $itemData['recurrence'],
                                'category_id' => $itemData['category_id'] ?? null,
                                'notes' => $itemData['notes'] ?? null,
                            ]);
                            $existingItemIds[] = $newItem->id;
                        }
                    }
                }

                $period->items()->whereNotIn('id', $existingItemIds)->delete();
                $period->recalculateTotals();
            }

            $plan->periods()->whereNotIn('id', $existingPeriodIds)->delete();
        });

        $plan->refresh();
        $plan->load('periods.items.category');

        return response()->json([
            'message' => 'Financial plan updated successfully',
            'data' => new FinancialPlanResource($plan),
        ]);
    }

    public function destroy(FinancialPlan $plan): JsonResponse
    {
        $this->authorizeAccess($plan);

        $plan->delete();

        return response()->json([
            'message' => 'Financial plan deleted successfully',
        ]);
    }

    public function compare(FinancialPlan $plan): JsonResponse
    {
        $this->authorizeAccess($plan);

        $plan->load('periods.items.category');

        $comparison = $this->planComparison->compare($plan, auth()->id());

        return response()->json([
            'data' => [
                'plan' => new FinancialPlanResource($plan),
                'comparison' => $comparison,
            ],
        ]);
    }

    protected function authorizeAccess(FinancialPlan $plan): void
    {
        if ($plan->user_id !== auth()->id()) {
            abort(403);
        }
    }
}
