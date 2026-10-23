<?php

namespace Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\DB;
use Inertia\{Inertia, Response};
use Modules\Finance\Models\{Category, Currency, FinancialPlan, RecurringTransaction};
use Modules\Finance\Services\{PlanComparisonService, RecurringTransactionService};

class FinancialPlanController extends Controller
{
    public function index(RecurringTransactionService $recurringService): Response
    {
        $userId = auth()->id();

        $plans = FinancialPlan::forUser($userId)
            ->with('periods')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($plan) => [
                'id' => $plan->id,
                'name' => $plan->name,
                'description' => $plan->description,
                'start_year' => $plan->start_year,
                'end_year' => $plan->end_year,
                'year_span' => $plan->year_span,
                'currency_code' => $plan->currency_code,
                'status' => $plan->status,
                'total_planned_income' => $plan->total_planned_income,
                'total_planned_expense' => $plan->total_planned_expense,
                'net_planned' => $plan->net_planned,
                'created_at' => $plan->created_at,
            ]);

        $recurringProjection = $recurringService->getMonthlyProjection($userId);

        $upcomingRecurrings = RecurringTransaction::forUser($userId)
            ->active()
            ->upcoming(7)
            ->with(['account', 'category'])
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'transaction_type' => $r->transaction_type,
                'amount' => $r->amount,
                'currency_code' => $r->currency_code,
                'frequency' => $r->frequency,
                'next_run_date' => $r->next_run_date->toDateString(),
                'category' => $r->category ? [
                    'name' => $r->category->name,
                    'color' => $r->category->color,
                    'is_passive' => $r->category->is_passive,
                ] : null,
            ]);

        return Inertia::render('Finance::plans/index', [
            'plans' => $plans,
            'recurringProjection' => $recurringProjection,
            'upcomingRecurrings' => $upcomingRecurrings,
        ]);
    }

    public function create(): Response
    {
        $currencies = Currency::where('active', true)->orderBy('code')->get();
        $categories = Category::where('user_id', auth()->id())
            ->orWhereNull('user_id')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return Inertia::render('Finance::plans/create', [
            'currencies' => $currencies,
            'categories' => $categories,
            'currentYear' => (int) date('Y'),
        ]);
    }

    public function store(Request $request): RedirectResponse
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

        DB::transaction(function () use ($validated) {
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
        });

        return redirect()
            ->route('dashboard.finance.plans.index')
            ->with('success', 'Financial plan created successfully.');
    }

    public function show(FinancialPlan $plan): Response
    {
        $this->authorizeAccess($plan);

        $plan->load(['periods.items.category', 'currency']);

        $currencies = Currency::where('active', true)->orderBy('code')->get();
        $categories = Category::where('user_id', auth()->id())
            ->orWhereNull('user_id')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return Inertia::render('Finance::plans/show', [
            'plan' => $this->formatPlan($plan),
            'currencies' => $currencies,
            'categories' => $categories,
        ]);
    }

    public function update(Request $request, FinancialPlan $plan): RedirectResponse
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

        return redirect()
            ->route('dashboard.finance.plans.show', $plan)
            ->with('success', 'Financial plan updated successfully.');
    }

    public function destroy(FinancialPlan $plan): RedirectResponse
    {
        $this->authorizeAccess($plan);

        $plan->delete();

        return redirect()
            ->route('dashboard.finance.plans.index')
            ->with('success', 'Financial plan deleted successfully.');
    }

    public function compare(FinancialPlan $plan): Response
    {
        $this->authorizeAccess($plan);

        $plan->load(['periods.items.category']);

        $comparison = app(PlanComparisonService::class)->compare($plan, auth()->id());

        return Inertia::render('Finance::plans/compare', [
            'plan' => $this->formatPlan($plan),
            'comparison' => $comparison,
        ]);
    }

    protected function authorizeAccess(FinancialPlan $plan): void
    {
        if ($plan->user_id !== auth()->id()) {
            abort(403);
        }
    }

    protected function formatPlan(FinancialPlan $plan): array
    {
        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'description' => $plan->description,
            'start_year' => $plan->start_year,
            'end_year' => $plan->end_year,
            'year_span' => $plan->year_span,
            'currency_code' => $plan->currency_code,
            'status' => $plan->status,
            'total_planned_income' => $plan->total_planned_income,
            'total_planned_expense' => $plan->total_planned_expense,
            'net_planned' => $plan->net_planned,
            'periods' => $plan->periods->map(fn ($period) => [
                'id' => $period->id,
                'year' => $period->year,
                'planned_income' => $period->planned_income,
                'planned_expense' => $period->planned_expense,
                'net_planned' => $period->net_planned,
                'notes' => $period->notes,
                'items' => $period->items->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'type' => $item->type,
                    'planned_amount' => $item->planned_amount,
                    'recurrence' => $item->recurrence,
                    'category_id' => $item->category_id,
                    'category' => $item->category ? [
                        'id' => $item->category->id,
                        'name' => $item->category->name,
                        'color' => $item->category->color,
                    ] : null,
                    'notes' => $item->notes,
                ]),
            ]),
            'created_at' => $plan->created_at,
            'updated_at' => $plan->updated_at,
        ];
    }
}
