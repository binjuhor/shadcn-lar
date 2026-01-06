<?php

namespace Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\Category;
use Modules\Finance\Models\RecurringTransaction;
use Modules\Finance\Services\RecurringTransactionService;

class RecurringTransactionController extends Controller
{
    public function __construct(
        protected RecurringTransactionService $recurringService
    ) {}

    public function index(): Response
    {
        $userId = auth()->id();

        $recurrings = RecurringTransaction::forUser($userId)
            ->with(['account', 'category'])
            ->orderBy('is_active', 'desc')
            ->orderBy('next_run_date')
            ->get();

        $upcoming = $this->recurringService->getUpcoming($userId, 30);
        $projection = $this->recurringService->getMonthlyProjection($userId);

        $accounts = Account::where('user_id', $userId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $categories = Category::userCategories($userId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return Inertia::render('Finance::recurring-transactions/index', [
            'recurrings' => $recurrings,
            'upcoming' => $upcoming,
            'projection' => $projection,
            'accounts' => $accounts,
            'categories' => $categories,
        ]);
    }

    public function create(): Response
    {
        $userId = auth()->id();

        $accounts = Account::where('user_id', $userId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $categories = Category::userCategories($userId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return Inertia::render('Finance::recurring-transactions/create', [
            'accounts' => $accounts,
            'categories' => $categories,
        ]);
    }

    public function store(Request $request): RedirectResponse
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

        // Verify account belongs to user and get currency
        $account = Account::where('id', $validated['account_id'])
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $validated['currency_code'] = $account->currency_code;

        $this->recurringService->create($validated);

        return Redirect::route('dashboard.finance.recurring-transactions.index')
            ->with('success', 'Recurring transaction created successfully');
    }

    public function edit(RecurringTransaction $recurringTransaction): Response
    {
        $this->authorizeAccess($recurringTransaction);

        $userId = auth()->id();

        $accounts = Account::where('user_id', $userId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $categories = Category::userCategories($userId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $preview = $this->recurringService->getPreview($recurringTransaction, 12);

        return Inertia::render('Finance::recurring-transactions/edit', [
            'recurring' => $recurringTransaction->load(['account', 'category']),
            'accounts' => $accounts,
            'categories' => $categories,
            'preview' => $preview,
        ]);
    }

    public function update(Request $request, RecurringTransaction $recurringTransaction): RedirectResponse
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
            'end_date' => ['nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
            'auto_create' => ['sometimes', 'boolean'],
        ]);

        $this->recurringService->update($recurringTransaction, $validated);

        return Redirect::back()->with('success', 'Recurring transaction updated successfully');
    }

    public function destroy(RecurringTransaction $recurringTransaction): RedirectResponse
    {
        $this->authorizeAccess($recurringTransaction);

        $recurringTransaction->delete();

        return Redirect::route('dashboard.finance.recurring-transactions.index')
            ->with('success', 'Recurring transaction deleted successfully');
    }

    public function toggle(RecurringTransaction $recurringTransaction): RedirectResponse
    {
        $this->authorizeAccess($recurringTransaction);

        if ($recurringTransaction->is_active) {
            $this->recurringService->pause($recurringTransaction);
            $message = 'Recurring transaction paused';
        } else {
            $this->recurringService->resume($recurringTransaction);
            $message = 'Recurring transaction resumed';
        }

        return Redirect::back()->with('success', $message);
    }

    public function preview(RecurringTransaction $recurringTransaction)
    {
        $this->authorizeAccess($recurringTransaction);

        $preview = $this->recurringService->getPreview($recurringTransaction, 24);

        return response()->json($preview);
    }

    public function process(): RedirectResponse
    {
        $result = $this->recurringService->processDue();

        return Redirect::back()->with('success', "Processed {$result['processed']} recurring transaction(s)");
    }

    protected function authorizeAccess(RecurringTransaction $recurringTransaction): void
    {
        if ($recurringTransaction->user_id !== auth()->id()) {
            abort(403);
        }
    }
}
