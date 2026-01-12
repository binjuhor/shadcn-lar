<?php

namespace Modules\Finance\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Finance\Http\Resources\AccountResource;
use Modules\Finance\Models\Account;

class AccountApiController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Account::with('currency')
            ->where('user_id', auth()->id());

        if ($request->boolean('active_only', true)) {
            $query->where('is_active', true);
        }

        if ($request->has('type')) {
            $query->where('account_type', $request->type);
        }

        $accounts = $query->orderBy('name')->get();

        return AccountResource::collection($accounts);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'account_type' => ['required', 'in:bank,credit_card,investment,cash,loan,e_wallet,other'],
            'currency_code' => ['required', 'string', 'size:3', 'exists:currencies,code'],
            'initial_balance' => ['required', 'numeric'],
            'description' => ['nullable', 'string', 'max:1000'],
            'color' => ['nullable', 'string', 'max:7'],
            'is_active' => ['boolean'],
            'is_default_payment' => ['boolean'],
            'exclude_from_total' => ['boolean'],
        ]);

        $account = Account::create([
            'user_id' => auth()->id(),
            'name' => $validated['name'],
            'account_type' => $validated['account_type'],
            'currency_code' => $validated['currency_code'],
            'initial_balance' => $validated['initial_balance'],
            'current_balance' => $validated['initial_balance'],
            'description' => $validated['description'] ?? null,
            'color' => $validated['color'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'exclude_from_total' => $validated['exclude_from_total'] ?? false,
        ]);

        if ($validated['is_default_payment'] ?? false) {
            $account->setAsDefaultPayment();
        }

        $account->load('currency');

        return response()->json([
            'message' => 'Account created successfully',
            'data' => new AccountResource($account),
        ], 201);
    }

    public function show(Account $account): JsonResponse
    {
        $this->authorize('view', $account);

        $account->load('currency');

        return response()->json([
            'data' => new AccountResource($account),
        ]);
    }

    public function update(Request $request, Account $account): JsonResponse
    {
        $this->authorize('update', $account);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'account_type' => ['sometimes', 'in:bank,credit_card,investment,cash,loan,e_wallet,other'],
            'currency_code' => ['sometimes', 'string', 'size:3', 'exists:currencies,code'],
            'initial_balance' => ['sometimes', 'numeric'],
            'current_balance' => ['sometimes', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:1000'],
            'color' => ['nullable', 'string', 'max:7'],
            'is_active' => ['sometimes', 'boolean'],
            'is_default_payment' => ['sometimes', 'boolean'],
            'exclude_from_total' => ['sometimes', 'boolean'],
        ]);

        $isCreditAccount = in_array($account->account_type, ['credit_card', 'loan']);

        if (isset($validated['initial_balance'])) {
            if ($isCreditAccount) {
                // For credit accounts: if current_balance is NOT provided, adjust it by the limit difference
                if (! isset($validated['current_balance'])) {
                    $limitDiff = $validated['initial_balance'] - $account->initial_balance;
                    $validated['current_balance'] = $account->current_balance + $limitDiff;
                }
            } else {
                // For regular accounts: initial_balance = current_balance
                $validated['current_balance'] = $validated['initial_balance'];
            }
        }

        $setAsDefault = $validated['is_default_payment'] ?? false;
        unset($validated['is_default_payment']);

        $account->update($validated);

        if ($setAsDefault) {
            $account->setAsDefaultPayment();
        } elseif ($account->is_default_payment && ! $setAsDefault) {
            $account->update(['is_default_payment' => false]);
        }

        $account->load('currency');

        return response()->json([
            'message' => 'Account updated successfully',
            'data' => new AccountResource($account),
        ]);
    }

    public function destroy(Account $account): JsonResponse
    {
        $this->authorize('delete', $account);

        if ($account->transactions()->exists()) {
            return response()->json([
                'message' => 'Cannot delete account with existing transactions',
            ], 422);
        }

        $account->delete();

        return response()->json([
            'message' => 'Account deleted successfully',
        ]);
    }

    public function summary(): JsonResponse
    {
        $userId = auth()->id();
        $allAccounts = Account::where('user_id', $userId)
            ->where('is_active', true)
            ->get();

        $includedAccounts = $allAccounts->where('exclude_from_total', false);

        // Assets: only from accounts where exclude_from_total = false
        $totalAssets = $includedAccounts
            ->whereIn('account_type', ['bank', 'investment', 'cash', 'e_wallet'])
            ->where('current_balance', '>', 0)
            ->sum('current_balance');

        // Liabilities: from ALL credit cards/loans (debt is always tracked)
        $totalLiabilities = $allAccounts
            ->whereIn('account_type', ['credit_card', 'loan'])
            ->sum(function ($account) {
                $amountOwed = $account->initial_balance - $account->current_balance;

                return $amountOwed > 0 ? $amountOwed : 0;
            });

        return response()->json([
            'data' => [
                'total_assets' => (float) $totalAssets,
                'total_liabilities' => (float) $totalLiabilities,
                'net_worth' => (float) ($totalAssets - $totalLiabilities),
            ],
        ]);
    }
}
