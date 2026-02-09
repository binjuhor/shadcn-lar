<?php

namespace Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Redirect;
use Inertia\{Inertia, Response};
use Modules\Finance\Models\{Account, Currency, SavingsContribution, SavingsGoal, Transaction};
use Modules\Finance\Services\SavingsGoalService;

class SavingsGoalController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private SavingsGoalService $savingsGoalService
    ) {}

    public function index(): Response
    {
        $userId = auth()->id();

        $goals = SavingsGoal::with(['currency', 'targetAccount', 'contributions'])
            ->where('user_id', $userId)
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 WHEN status = 'paused' THEN 1 ELSE 2 END")
            ->orderBy('created_at', 'desc')
            ->get();

        $accounts = Account::where('user_id', $userId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $currencies = Currency::orderBy('code')->get();

        return Inertia::render('Finance::savings-goals/index', [
            'goals' => $goals,
            'accounts' => $accounts,
            'currencies' => $currencies,
        ]);
    }

    public function create(): Response
    {
        $userId = auth()->id();

        $accounts = Account::where('user_id', $userId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $currencies = Currency::orderBy('code')->get();

        return Inertia::render('Finance::savings-goals/create', [
            'accounts' => $accounts,
            'currencies' => $currencies,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'icon' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:7'],
            'target_amount' => ['required', 'integer', 'min:1'],
            'currency_code' => ['required', 'string', 'size:3', 'exists:currencies,code'],
            'target_date' => ['nullable', 'date', 'after:today'],
            'target_account_id' => ['nullable', 'exists:finance_accounts,id'],
            'is_active' => ['boolean'],
        ]);

        $this->savingsGoalService->createGoal([
            ...$validated,
            'user_id' => auth()->id(),
        ]);

        return Redirect::route('dashboard.finance.savings-goals.index')
            ->with('success', 'Savings goal created successfully');
    }

    public function show(SavingsGoal $savingsGoal): Response
    {
        $this->authorize('view', $savingsGoal);

        $userId = auth()->id();

        $savingsGoal->load(['currency', 'targetAccount', 'contributions.transaction']);

        $linkedTransactionIds = $savingsGoal->contributions()
            ->whereNotNull('transaction_id')
            ->pluck('transaction_id');

        $availableTransactions = Transaction::where('user_id', $userId)
            ->whereNotIn('id', $linkedTransactionIds)
            ->where('transaction_type', 'income')
            ->orderBy('transaction_date', 'desc')
            ->limit(50)
            ->get();

        $accounts = Account::where('user_id', $userId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return Inertia::render('Finance::savings-goals/show', [
            'goal' => $savingsGoal,
            'availableTransactions' => $availableTransactions,
            'accounts' => $accounts,
        ]);
    }

    public function edit(SavingsGoal $savingsGoal): Response
    {
        $this->authorize('update', $savingsGoal);

        $userId = auth()->id();

        $accounts = Account::where('user_id', $userId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $currencies = Currency::orderBy('code')->get();

        return Inertia::render('Finance::savings-goals/edit', [
            'goal' => $savingsGoal,
            'accounts' => $accounts,
            'currencies' => $currencies,
        ]);
    }

    public function update(Request $request, SavingsGoal $savingsGoal): RedirectResponse
    {
        $this->authorize('update', $savingsGoal);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'icon' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:7'],
            'target_amount' => ['sometimes', 'integer', 'min:1'],
            'currency_code' => ['sometimes', 'string', 'size:3', 'exists:currencies,code'],
            'target_date' => ['nullable', 'date'],
            'target_account_id' => ['nullable', 'exists:finance_accounts,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $this->savingsGoalService->updateGoal($savingsGoal, $validated);

        return Redirect::back()->with('success', 'Savings goal updated successfully');
    }

    public function destroy(SavingsGoal $savingsGoal): RedirectResponse
    {
        $this->authorize('delete', $savingsGoal);

        $savingsGoal->delete();

        return Redirect::route('dashboard.finance.savings-goals.index')
            ->with('success', 'Savings goal deleted successfully');
    }

    public function contribute(Request $request, SavingsGoal $savingsGoal): RedirectResponse
    {
        $this->authorize('contribute', $savingsGoal);

        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'contribution_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $this->savingsGoalService->addContribution($savingsGoal, $validated);

        return Redirect::back()->with('success', 'Contribution added successfully');
    }

    public function withdraw(Request $request, SavingsGoal $savingsGoal): RedirectResponse
    {
        $this->authorize('contribute', $savingsGoal);

        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'contribution_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->savingsGoalService->withdrawContribution($savingsGoal, $validated);

            return Redirect::back()->with('success', 'Withdrawal recorded successfully');
        } catch (\Exception $e) {
            return Redirect::back()->withErrors(['amount' => $e->getMessage()]);
        }
    }

    public function linkTransaction(Request $request, SavingsGoal $savingsGoal): RedirectResponse
    {
        $this->authorize('contribute', $savingsGoal);

        $validated = $request->validate([
            'transaction_id' => ['required', 'exists:finance_transactions,id'],
        ]);

        $transaction = Transaction::findOrFail($validated['transaction_id']);

        try {
            $this->savingsGoalService->linkTransaction($savingsGoal, $transaction);

            return Redirect::back()->with('success', 'Transaction linked successfully');
        } catch (\Exception $e) {
            return Redirect::back()->withErrors(['transaction_id' => $e->getMessage()]);
        }
    }

    public function unlinkContribution(SavingsGoal $savingsGoal, SavingsContribution $contribution): RedirectResponse
    {
        $this->authorize('update', $savingsGoal);

        $this->savingsGoalService->unlinkContribution($contribution);

        return Redirect::back()->with('success', 'Contribution removed successfully');
    }

    public function pause(SavingsGoal $savingsGoal): RedirectResponse
    {
        $this->authorize('update', $savingsGoal);

        $this->savingsGoalService->pauseGoal($savingsGoal);

        return Redirect::back()->with('success', 'Savings goal paused');
    }

    public function resume(SavingsGoal $savingsGoal): RedirectResponse
    {
        $this->authorize('update', $savingsGoal);

        $this->savingsGoalService->resumeGoal($savingsGoal);

        return Redirect::back()->with('success', 'Savings goal resumed');
    }

    public function sync(SavingsGoal $savingsGoal): RedirectResponse
    {
        $this->authorize('update', $savingsGoal);

        if (! $savingsGoal->target_account_id) {
            return Redirect::back()->withErrors(['sync' => 'No linked account to sync with']);
        }

        $this->savingsGoalService->syncWithLinkedAccount($savingsGoal);
        $this->savingsGoalService->checkCompletion($savingsGoal->fresh());

        return Redirect::back()->with('success', 'Savings goal synced with account balance');
    }

    public function transfer(Request $request, SavingsGoal $savingsGoal): RedirectResponse
    {
        $this->authorize('contribute', $savingsGoal);

        $validated = $request->validate([
            'from_account_id' => ['required', 'exists:finance_accounts,id'],
            'amount' => ['required', 'integer', 'min:1'],
            'transfer_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $this->savingsGoalService->transferToGoal($savingsGoal, $validated);

        return Redirect::back()->with('success', 'Transfer completed successfully');
    }
}
