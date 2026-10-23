<?php

namespace Modules\Finance\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Finance\Http\Requests\Loan\{StoreLoanRepaymentRequest, StoreLoanRequest, UpdateLoanRequest};
use Modules\Finance\Http\Resources\{LoanRepaymentResource, LoanResource};
use Modules\Finance\Models\{Loan, LoanRepayment};
use Modules\Finance\Services\{LoanReceivableService, LoanService, UserCurrencyConversionService};

class LoanApiController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected LoanService $loanService,
        protected LoanReceivableService $receivableService,
        protected UserCurrencyConversionService $conversionService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:active,partially_repaid,repaid,written_off'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $loans = Loan::with('repayments')
            ->where('user_id', $request->user()->id)
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($validated['search'] ?? null, fn ($query, $search) => $query->where(fn ($nested) => $nested
                ->where('borrower_name', 'like', "%{$search}%")
                ->orWhere('message', 'like', "%{$search}%")))
            ->latest('loan_date')
            ->latest('id')
            ->paginate($validated['per_page'] ?? 15);

        return LoanResource::collection($loans);
    }

    public function store(StoreLoanRequest $request): JsonResponse
    {
        $loan = $this->loanService->createLoan([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ])->load('repayments');

        return response()->json(['message' => 'Loan created successfully', 'data' => new LoanResource($loan)], 201);
    }

    public function show(Loan $loan): JsonResponse
    {
        $this->authorize('view', $loan);

        return response()->json(['data' => new LoanResource($loan->load('repayments'))]);
    }

    public function update(UpdateLoanRequest $request, Loan $loan): JsonResponse
    {
        $this->authorize('update', $loan);
        $updated = $this->loanService->updateLoan($loan, $request->validated())->load('repayments');

        return response()->json(['message' => 'Loan updated successfully', 'data' => new LoanResource($updated)]);
    }

    public function destroy(Loan $loan): JsonResponse
    {
        $this->authorize('delete', $loan);
        $loan->delete();

        return response()->json(['message' => 'Loan deleted successfully']);
    }

    public function summary(Request $request): JsonResponse
    {
        $groups = Loan::where('user_id', $request->user()->id)
            ->outstanding()
            ->selectRaw('currency_code, SUM(CASE WHEN amount > amount_repaid THEN amount - amount_repaid ELSE 0 END) as outstanding_amount, COUNT(*) as loans_count')
            ->groupBy('currency_code')
            ->get()
            ->map(fn ($row) => [
                'currency_code' => $row->currency_code,
                'outstanding_amount' => (float) $row->outstanding_amount,
                'loans_count' => (int) $row->loans_count,
            ]);

        return response()->json(['data' => [
            'loan_receivables' => $this->receivableService->totalForUser($request->user()),
            'currency_code' => $this->conversionService->defaultCurrency($request->user()),
            'by_currency' => $groups,
        ]]);
    }

    public function storeRepayment(StoreLoanRepaymentRequest $request, Loan $loan): JsonResponse
    {
        $repayment = $this->loanService->addRepayment($loan, $request->validated());

        return response()->json([
            'message' => 'Repayment created successfully',
            'data' => new LoanRepaymentResource($repayment),
        ], 201);
    }

    public function destroyRepayment(Loan $loan, LoanRepayment $repayment): JsonResponse
    {
        $this->authorize('update', $loan);
        abort_unless($repayment->loan_id === $loan->id, 404);
        $this->loanService->deleteRepayment($repayment);

        return response()->json(['message' => 'Repayment deleted successfully']);
    }
}
