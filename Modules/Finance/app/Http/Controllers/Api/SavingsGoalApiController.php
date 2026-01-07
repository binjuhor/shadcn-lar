<?php

namespace Modules\Finance\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Finance\Http\Resources\SavingsContributionResource;
use Modules\Finance\Http\Resources\SavingsGoalResource;
use Modules\Finance\Models\SavingsContribution;
use Modules\Finance\Models\SavingsGoal;
use Modules\Finance\Models\Transaction;
use Modules\Finance\Services\SavingsGoalService;

class SavingsGoalApiController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected SavingsGoalService $savingsGoalService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = SavingsGoal::with(['currency', 'targetAccount'])
            ->withCount('contributions')
            ->where('user_id', auth()->id());

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->boolean('active_only', false)) {
            $query->active();
        }

        $goals = $query->orderByRaw("CASE WHEN status = 'active' THEN 0 WHEN status = 'paused' THEN 1 ELSE 2 END")
            ->orderBy('created_at', 'desc')
            ->get();

        return SavingsGoalResource::collection($goals);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'icon' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:7'],
            'target_amount' => ['required', 'numeric', 'min:0.01'],
            'currency_code' => ['required', 'string', 'size:3', 'exists:currencies,code'],
            'target_date' => ['nullable', 'date', 'after:today'],
            'target_account_id' => ['nullable', 'exists:finance_accounts,id'],
            'is_active' => ['boolean'],
        ]);

        $goal = $this->savingsGoalService->createGoal([
            ...$validated,
            'user_id' => auth()->id(),
        ]);

        $goal->load(['currency', 'targetAccount']);

        return response()->json([
            'message' => 'Savings goal created successfully',
            'data' => new SavingsGoalResource($goal),
        ], 201);
    }

    public function show(SavingsGoal $savingsGoal): JsonResponse
    {
        $this->authorize('view', $savingsGoal);

        $savingsGoal->load(['currency', 'targetAccount', 'contributions.transaction']);

        return response()->json([
            'data' => new SavingsGoalResource($savingsGoal),
        ]);
    }

    public function update(Request $request, SavingsGoal $savingsGoal): JsonResponse
    {
        $this->authorize('update', $savingsGoal);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'icon' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:7'],
            'target_amount' => ['sometimes', 'numeric', 'min:0.01'],
            'currency_code' => ['sometimes', 'string', 'size:3', 'exists:currencies,code'],
            'target_date' => ['nullable', 'date'],
            'target_account_id' => ['nullable', 'exists:finance_accounts,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $goal = $this->savingsGoalService->updateGoal($savingsGoal, $validated);
        $goal->load(['currency', 'targetAccount']);

        return response()->json([
            'message' => 'Savings goal updated successfully',
            'data' => new SavingsGoalResource($goal),
        ]);
    }

    public function destroy(SavingsGoal $savingsGoal): JsonResponse
    {
        $this->authorize('delete', $savingsGoal);

        $savingsGoal->delete();

        return response()->json([
            'message' => 'Savings goal deleted successfully',
        ]);
    }

    public function contribute(Request $request, SavingsGoal $savingsGoal): JsonResponse
    {
        $this->authorize('update', $savingsGoal);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'contribution_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $contribution = $this->savingsGoalService->addContribution($savingsGoal, $validated);
            $contribution->load('transaction');

            return response()->json([
                'message' => 'Contribution added successfully',
                'data' => new SavingsContributionResource($contribution),
                'goal' => new SavingsGoalResource($savingsGoal->fresh(['currency', 'targetAccount'])),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function withdraw(Request $request, SavingsGoal $savingsGoal): JsonResponse
    {
        $this->authorize('update', $savingsGoal);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'contribution_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $contribution = $this->savingsGoalService->withdrawContribution($savingsGoal, $validated);
            $contribution->load('transaction');

            return response()->json([
                'message' => 'Withdrawal recorded successfully',
                'data' => new SavingsContributionResource($contribution),
                'goal' => new SavingsGoalResource($savingsGoal->fresh(['currency', 'targetAccount'])),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function transfer(Request $request, SavingsGoal $savingsGoal): JsonResponse
    {
        $this->authorize('update', $savingsGoal);

        $validated = $request->validate([
            'from_account_id' => ['required', 'exists:finance_accounts,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'transfer_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $contribution = $this->savingsGoalService->transferToGoal($savingsGoal, $validated);
            $contribution->load('transaction');

            return response()->json([
                'message' => 'Transfer completed successfully',
                'data' => new SavingsContributionResource($contribution),
                'goal' => new SavingsGoalResource($savingsGoal->fresh(['currency', 'targetAccount'])),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function linkTransaction(Request $request, SavingsGoal $savingsGoal): JsonResponse
    {
        $this->authorize('update', $savingsGoal);

        $validated = $request->validate([
            'transaction_id' => ['required', 'exists:finance_transactions,id'],
        ]);

        $transaction = Transaction::findOrFail($validated['transaction_id']);

        try {
            $contribution = $this->savingsGoalService->linkTransaction($savingsGoal, $transaction);
            $contribution->load('transaction');

            return response()->json([
                'message' => 'Transaction linked successfully',
                'data' => new SavingsContributionResource($contribution),
                'goal' => new SavingsGoalResource($savingsGoal->fresh(['currency', 'targetAccount'])),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function unlinkContribution(SavingsGoal $savingsGoal, SavingsContribution $contribution): JsonResponse
    {
        $this->authorize('update', $savingsGoal);

        if ($contribution->savings_goal_id !== $savingsGoal->id) {
            return response()->json([
                'message' => 'Contribution does not belong to this goal',
            ], 422);
        }

        $this->savingsGoalService->unlinkContribution($contribution);

        return response()->json([
            'message' => 'Contribution removed successfully',
            'goal' => new SavingsGoalResource($savingsGoal->fresh(['currency', 'targetAccount'])),
        ]);
    }

    public function pause(SavingsGoal $savingsGoal): JsonResponse
    {
        $this->authorize('update', $savingsGoal);

        $goal = $this->savingsGoalService->pauseGoal($savingsGoal);
        $goal->load(['currency', 'targetAccount']);

        return response()->json([
            'message' => 'Savings goal paused',
            'data' => new SavingsGoalResource($goal),
        ]);
    }

    public function resume(SavingsGoal $savingsGoal): JsonResponse
    {
        $this->authorize('update', $savingsGoal);

        $goal = $this->savingsGoalService->resumeGoal($savingsGoal);
        $goal->load(['currency', 'targetAccount']);

        return response()->json([
            'message' => 'Savings goal resumed',
            'data' => new SavingsGoalResource($goal),
        ]);
    }

    public function summary(): JsonResponse
    {
        $userId = auth()->id();

        $goals = SavingsGoal::where('user_id', $userId)->get();

        $activeGoals = $goals->where('status', 'active');
        $completedGoals = $goals->where('status', 'completed');

        $totalTarget = $activeGoals->sum('target_amount');
        $totalSaved = $activeGoals->sum('current_amount');

        return response()->json([
            'data' => [
                'total_target' => (float) $totalTarget,
                'total_saved' => (float) $totalSaved,
                'total_remaining' => (float) max($totalTarget - $totalSaved, 0),
                'overall_progress' => $totalTarget > 0 ? round(($totalSaved / $totalTarget) * 100, 2) : 0,
                'active_goals_count' => $activeGoals->count(),
                'completed_goals_count' => $completedGoals->count(),
                'total_goals_count' => $goals->count(),
            ],
        ]);
    }
}
