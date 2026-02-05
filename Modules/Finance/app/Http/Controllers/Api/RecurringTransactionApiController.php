<?php

namespace Modules\Finance\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Finance\Http\Resources\RecurringTransactionResource;
use Modules\Finance\Models\{Account, RecurringTransaction};
use Modules\Finance\Services\RecurringTransactionService;

class RecurringTransactionApiController extends Controller
{
    public function __construct(
        protected RecurringTransactionService $recurringService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $userId = auth()->id();

        $query = RecurringTransaction::forUser($userId)
            ->with(['account', 'category']);

        if ($request->boolean('active_only', false)) {
            $query->active();
        }

        if ($request->has('type')) {
            $query->where('transaction_type', $request->type);
        }

        if ($request->has('frequency')) {
            $query->where('frequency', $request->frequency);
        }

        $recurrings = $query
            ->orderBy('is_active', 'desc')
            ->orderBy('next_run_date')
            ->get();

        return RecurringTransactionResource::collection($recurrings);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'account_id' => ['required', 'exists:finance_accounts,id'],
            'category_id' => ['nullable', 'exists:finance_categories,id'],
            'transaction_type' => ['required', 'in:income,expense'],
            'amount' => ['required', 'integer', 'min:1'],
            'frequency' => ['required', 'in:daily,weekly,monthly,yearly'],
            'day_of_week' => ['nullable', 'integer', 'min:0', 'max:6'],
            'day_of_month' => ['nullable', 'integer', 'min:1', 'max:31'],
            'month_of_year' => ['nullable', 'integer', 'min:1', 'max:12'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'is_active' => ['boolean'],
            'auto_create' => ['boolean'],
        ]);

        $account = Account::where('id', $validated['account_id'])
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $validated['currency_code'] = $account->currency_code;

        $recurring = $this->recurringService->create($validated);
        $recurring->load(['account', 'category']);

        return response()->json([
            'message' => 'Recurring transaction created successfully',
            'data' => new RecurringTransactionResource($recurring),
        ], 201);
    }

    public function show(RecurringTransaction $recurringTransaction): JsonResponse
    {
        $this->authorizeAccess($recurringTransaction);

        $recurringTransaction->load(['account', 'category']);

        return response()->json([
            'data' => new RecurringTransactionResource($recurringTransaction),
        ]);
    }

    public function update(Request $request, RecurringTransaction $recurringTransaction): JsonResponse
    {
        $this->authorizeAccess($recurringTransaction);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'account_id' => ['sometimes', 'exists:finance_accounts,id'],
            'category_id' => ['nullable', 'exists:finance_categories,id'],
            'transaction_type' => ['sometimes', 'in:income,expense'],
            'amount' => ['sometimes', 'integer', 'min:1'],
            'frequency' => ['sometimes', 'in:daily,weekly,monthly,yearly'],
            'day_of_week' => ['nullable', 'integer', 'min:0', 'max:6'],
            'day_of_month' => ['nullable', 'integer', 'min:1', 'max:31'],
            'month_of_year' => ['nullable', 'integer', 'min:1', 'max:12'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
            'auto_create' => ['sometimes', 'boolean'],
        ]);

        $this->recurringService->update($recurringTransaction, $validated);
        $recurringTransaction->refresh();
        $recurringTransaction->load(['account', 'category']);

        return response()->json([
            'message' => 'Recurring transaction updated successfully',
            'data' => new RecurringTransactionResource($recurringTransaction),
        ]);
    }

    public function destroy(RecurringTransaction $recurringTransaction): JsonResponse
    {
        $this->authorizeAccess($recurringTransaction);

        $recurringTransaction->delete();

        return response()->json([
            'message' => 'Recurring transaction deleted successfully',
        ]);
    }

    public function toggle(RecurringTransaction $recurringTransaction): JsonResponse
    {
        $this->authorizeAccess($recurringTransaction);

        if ($recurringTransaction->is_active) {
            $this->recurringService->pause($recurringTransaction);
            $message = 'Recurring transaction paused';
        } else {
            $this->recurringService->resume($recurringTransaction);
            $message = 'Recurring transaction resumed';
        }

        $recurringTransaction->refresh();
        $recurringTransaction->load(['account', 'category']);

        return response()->json([
            'message' => $message,
            'data' => new RecurringTransactionResource($recurringTransaction),
        ]);
    }

    public function preview(RecurringTransaction $recurringTransaction): JsonResponse
    {
        $this->authorizeAccess($recurringTransaction);

        $preview = $this->recurringService->getPreview($recurringTransaction, 24);

        return response()->json([
            'data' => $preview,
        ]);
    }

    public function upcoming(Request $request): JsonResponse
    {
        $userId = auth()->id();
        $days = $request->integer('days', 30);

        $upcoming = $this->recurringService->getUpcoming($userId, $days);

        return response()->json([
            'data' => $upcoming,
        ]);
    }

    public function projection(Request $request): JsonResponse
    {
        $userId = auth()->id();
        $currencyCode = $request->get('currency');

        $projection = $this->recurringService->getMonthlyProjection($userId, $currencyCode);

        return response()->json([
            'data' => $projection,
        ]);
    }

    public function process(): JsonResponse
    {
        $result = $this->recurringService->processDue();

        return response()->json([
            'message' => "Processed {$result['processed']} recurring transaction(s)",
            'data' => $result,
        ]);
    }

    protected function authorizeAccess(RecurringTransaction $recurringTransaction): void
    {
        if ($recurringTransaction->user_id !== auth()->id()) {
            abort(403);
        }
    }
}
