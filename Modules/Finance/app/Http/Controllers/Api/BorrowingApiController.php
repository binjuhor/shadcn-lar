<?php

namespace Modules\Finance\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Finance\Http\Requests\Borrowing\{StoreBorrowingRepaymentRequest, StoreBorrowingRequest, UpdateBorrowingRequest};
use Modules\Finance\Http\Resources\{BorrowingRepaymentResource, BorrowingResource};
use Modules\Finance\Models\{Borrowing, BorrowingRepayment};
use Modules\Finance\Services\{BorrowingPayableService, BorrowingService, UserCurrencyConversionService};

class BorrowingApiController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected BorrowingService $borrowingService,
        protected BorrowingPayableService $payableService,
        protected UserCurrencyConversionService $conversionService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:active,partially_repaid,repaid,written_off'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $borrowings = Borrowing::with('repayments')
            ->where('user_id', $request->user()->id)
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($validated['search'] ?? null, fn ($query, $search) => $query->where(fn ($nested) => $nested
                ->where('lender_name', 'like', "%{$search}%")
                ->orWhere('message', 'like', "%{$search}%")))
            ->latest('borrowed_date')
            ->latest('id')
            ->paginate($validated['per_page'] ?? 15);

        return BorrowingResource::collection($borrowings);
    }

    public function store(StoreBorrowingRequest $request): JsonResponse
    {
        $borrowing = $this->borrowingService->createBorrowing([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ])->load('repayments');

        return response()->json(['message' => 'Borrowing created successfully', 'data' => new BorrowingResource($borrowing)], 201);
    }

    public function show(Borrowing $borrowing): JsonResponse
    {
        $this->authorize('view', $borrowing);

        return response()->json(['data' => new BorrowingResource($borrowing->load('repayments'))]);
    }

    public function update(UpdateBorrowingRequest $request, Borrowing $borrowing): JsonResponse
    {
        $this->authorize('update', $borrowing);
        $updated = $this->borrowingService->updateBorrowing($borrowing, $request->validated())->load('repayments');

        return response()->json(['message' => 'Borrowing updated successfully', 'data' => new BorrowingResource($updated)]);
    }

    public function destroy(Borrowing $borrowing): JsonResponse
    {
        $this->authorize('delete', $borrowing);
        $borrowing->delete();

        return response()->json(['message' => 'Borrowing deleted successfully']);
    }

    public function summary(Request $request): JsonResponse
    {
        $groups = Borrowing::where('user_id', $request->user()->id)
            ->outstanding()
            ->selectRaw('currency_code, SUM(CASE WHEN amount > amount_repaid THEN amount - amount_repaid ELSE 0 END) as outstanding_amount, COUNT(*) as borrowings_count')
            ->groupBy('currency_code')
            ->get()
            ->map(fn ($row) => [
                'currency_code' => $row->currency_code,
                'outstanding_amount' => (float) $row->outstanding_amount,
                'borrowings_count' => (int) $row->borrowings_count,
            ]);

        return response()->json(['data' => [
            'borrowings_payable' => $this->payableService->totalForUser($request->user()),
            'currency_code' => $this->conversionService->defaultCurrency($request->user()),
            'by_currency' => $groups,
        ]]);
    }

    public function storeRepayment(StoreBorrowingRepaymentRequest $request, Borrowing $borrowing): JsonResponse
    {
        $repayment = $this->borrowingService->addRepayment($borrowing, $request->validated());

        return response()->json([
            'message' => 'Repayment created successfully',
            'data' => new BorrowingRepaymentResource($repayment),
        ], 201);
    }

    public function destroyRepayment(Borrowing $borrowing, BorrowingRepayment $repayment): JsonResponse
    {
        $this->authorize('update', $borrowing);
        abort_unless($repayment->borrowing_id === $borrowing->id, 404);
        $this->borrowingService->deleteRepayment($repayment);

        return response()->json(['message' => 'Repayment deleted successfully']);
    }
}
