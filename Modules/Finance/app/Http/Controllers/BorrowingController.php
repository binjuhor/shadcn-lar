<?php

namespace Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\{JsonResponse, RedirectResponse, Request};
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Inertia\{Inertia, Response};
use Modules\Finance\Models\{Borrowing, BorrowingRepayment, Currency, Transaction};
use Modules\Finance\Services\{BillAttachmentService, BorrowingService};

class BorrowingController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private BorrowingService $borrowingService,
        private BillAttachmentService $billAttachmentService,
    ) {}

    public function index(Request $request): Response
    {
        $userId = auth()->id();
        $filters = $request->only(['search', 'status']);

        $borrowings = Borrowing::with(['currency', 'repayments', 'transaction.account'])
            ->where('user_id', $userId)
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('lender_name', 'like', "%{$search}%")
                        ->orWhere('message', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, function ($q, $status) {
                if ($status !== 'all') {
                    $q->where('status', $status);
                }
            })
            ->orderByRaw('COALESCE((SELECT transaction_date FROM finance_transactions WHERE finance_transactions.id = finance_borrowings.transaction_id), finance_borrowings.borrowed_date) DESC')
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        $currencies = Currency::orderBy('code')->get();

        // Summary respects search/status filters (excludes written_off when no status filter)
        $summary = Borrowing::where('user_id', $userId)
            ->when(
                empty($filters['status']) || $filters['status'] === 'all',
                fn ($q) => $q->whereNotIn('status', ['written_off'])
            )
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('lender_name', 'like', "%{$search}%")
                        ->orWhere('message', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, function ($q, $status) {
                if ($status !== 'all') {
                    $q->where('status', $status);
                }
            })
            ->selectRaw('currency_code, SUM(amount) as total_amount, SUM(amount_repaid) as total_repaid, COUNT(*) as total_count')
            ->groupBy('currency_code')
            ->get()
            ->map(fn ($row) => [
                'currency_code' => $row->currency_code,
                'total_amount' => (float) $row->total_amount,
                'total_repaid' => (float) $row->total_repaid,
                'total_outstanding' => (float) $row->total_amount - (float) $row->total_repaid,
                'total_count' => (int) $row->total_count,
            ])
            ->values();

        // Income transactions for the borrowing-linking picker (excludes transfer-side records)
        $incomeTransactions = Transaction::with('account')
            ->where('user_id', $userId)
            ->where('transaction_type', 'income')
            ->whereNull('transfer_transaction_id')
            ->orderBy('transaction_date', 'desc')
            ->limit(500)
            ->get(['id', 'account_id', 'transaction_type', 'description', 'notes', 'amount', 'currency_code', 'transaction_date']);

        return Inertia::render('Finance::borrowings/index', [
            'borrowings' => $borrowings,
            'currencies' => $currencies,
            'filters' => $filters,
            'summary' => $summary,
            'incomeTransactions' => $incomeTransactions,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateBorrowing($request);

        $borrowing = $this->borrowingService->createBorrowing([
            ...$validated,
            'user_id' => auth()->id(),
        ]);

        if ($request->hasFile('proofs')) {
            $this->billAttachmentService->attach($borrowing, 'proofs', $request->file('proofs'));
        }

        return Redirect::route('dashboard.finance.borrowings.index')
            ->with('success', 'Borrowing recorded successfully');
    }

    public function searchIncomeTransactions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $search = $validated['search'] ?? null;

        $transactions = Transaction::with('account')
            ->where('user_id', auth()->id())
            ->where('transaction_type', 'income')
            ->whereNull('transfer_transaction_id')
            ->when($search, function ($query, string $search) {
                $query->where(function ($sub) use ($search) {
                    $sub->where('description', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%");

                    if (is_numeric($search)) {
                        $sub->orWhere('amount', $search);
                    }
                });
            })
            ->orderBy('transaction_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get(['id', 'account_id', 'transaction_type', 'description', 'notes', 'amount', 'currency_code', 'transaction_date']);

        return response()->json(['data' => $transactions]);
    }

    public function update(Request $request, Borrowing $borrowing): RedirectResponse
    {
        $this->authorize('update', $borrowing);

        $validated = $this->validateBorrowing($request, partial: true);

        $this->borrowingService->updateBorrowing($borrowing, $validated);

        if ($request->hasFile('proofs')) {
            $this->billAttachmentService->attach($borrowing, 'proofs', $request->file('proofs'));
        }

        return Redirect::back()->with('success', 'Borrowing updated successfully');
    }

    public function destroy(Borrowing $borrowing): RedirectResponse
    {
        $this->authorize('delete', $borrowing);

        $borrowing->delete();

        return Redirect::route('dashboard.finance.borrowings.index')
            ->with('success', 'Borrowing deleted successfully');
    }

    public function storeRepayment(Request $request, Borrowing $borrowing): RedirectResponse
    {
        $this->authorize('update', $borrowing);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'repaid_at' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $this->borrowingService->addRepayment($borrowing, $validated);

        return Redirect::back()->with('success', 'Repayment recorded successfully');
    }

    public function destroyRepayment(Borrowing $borrowing, BorrowingRepayment $repayment): RedirectResponse
    {
        $this->authorize('update', $borrowing);

        $this->borrowingService->deleteRepayment($repayment);

        return Redirect::back()->with('success', 'Repayment removed successfully');
    }

    public function destroyProof(Borrowing $borrowing, int $mediaId): RedirectResponse
    {
        $this->authorize('update', $borrowing);

        $borrowing->deleteMedia($mediaId);

        return Redirect::back()->with('success', 'Image removed successfully');
    }

    private function validateBorrowing(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'transaction_id' => [
                'nullable',
                'integer',
                Rule::exists('finance_transactions', 'id')
                    ->where('user_id', auth()->id())
                    ->where('transaction_type', 'income')
                    ->whereNull('transfer_transaction_id'),
            ],
            'lender_name' => [$required, 'string', 'max:255'],
            'amount' => [$required, 'numeric', 'min:0.01'],
            'currency_code' => [$required, 'string', 'size:3', 'exists:currencies,code'],
            'borrowed_date' => [$required, 'date'],
            'due_date' => ['nullable', 'date'],
            'message' => ['nullable', 'string', 'max:2000'],
            'proofs' => ['nullable', 'array'],
            'proofs.*' => ['file', 'image', 'max:10240'],
        ]);
    }
}
