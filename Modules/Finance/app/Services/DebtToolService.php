<?php

namespace Modules\Finance\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Finance\Models\{Account, Borrowing, Category, Loan, Transaction};

/**
 * Money lent (loans, a receivable) and money borrowed (borrowings, a payable)
 * for the AI assistant. Every query is scoped to the acting user; business
 * rules (repayment limits, status) stay in LoanService / BorrowingService.
 *
 * New debts and repayments only move an account balance when the caller
 * passes `account_id`. The ledger entry is linked (debt `transaction_id`,
 * repayment `transaction_id`) so deleting a repayment reverses it.
 */
class DebtToolService
{
    /**
     * Direction-specific columns. `opening` is the account transaction type
     * when the debt starts; `repayment` is the type when it is paid back.
     */
    private const TYPES = [
        'loan' => [
            'model' => Loan::class,
            'person' => 'borrower_name',
            'date' => 'loan_date',
            'opening' => 'expense',
            'repayment' => 'income',
        ],
        'borrowing' => [
            'model' => Borrowing::class,
            'person' => 'lender_name',
            'date' => 'borrowed_date',
            'opening' => 'income',
            'repayment' => 'expense',
        ],
    ];

    private const LIMIT_MAX = 50;

    public function __construct(
        private LoanService $loans,
        private BorrowingService $borrowings,
        private TransactionService $transactions,
        private UserCurrencyConversionService $conversion,
    ) {}

    /** @return array<string, mixed> */
    public function getLoans(int $userId, array $filters = []): array
    {
        return $this->list('loan', $userId, $filters);
    }

    /** @return array<string, mixed> */
    public function getBorrowings(int $userId, array $filters = []): array
    {
        return $this->list('borrowing', $userId, $filters);
    }

    /**
     * Outstanding receivables vs payables per currency, per person, and the
     * overdue items, plus a net position converted to the default currency.
     *
     * @return array<string, mixed>
     */
    public function getDebtSummary(int $userId, array $filters = []): array
    {
        $user = User::findOrFail($userId);
        $currency = $this->conversion->defaultCurrency($user);
        $summary = ['default_currency' => $currency];
        $converted = [];
        $unconverted = [];

        foreach (self::TYPES as $type => $meta) {
            $items = $this->query($type, $userId)->outstanding()->get();
            $key = $type === 'loan' ? 'owed_to_me' : 'i_owe';

            $summary[$key] = [
                'count' => $items->count(),
                'by_currency' => $items->groupBy('currency_code')
                    ->map(fn ($group, string $code) => ['currency' => $code, 'outstanding' => (float) $group->sum('remaining_amount')])
                    ->values()->all(),
                'by_person' => $items->groupBy(fn (Model $item) => mb_strtolower(trim($item->{$meta['person']})).'|'.$item->currency_code)
                    ->map(fn ($group) => [
                        'name' => $group->first()->{$meta['person']},
                        'currency' => $group->first()->currency_code,
                        'outstanding' => (float) $group->sum('remaining_amount'),
                        'count' => $group->count(),
                    ])->sortByDesc('outstanding')->values()->take(20)->all(),
                'overdue' => $items->filter(fn (Model $item) => $item->due_date && $item->due_date->lt(today()))
                    ->sortBy('due_date')->take(20)->map(fn (Model $item) => $this->item($type, $item))->values()->all(),
            ];

            $converted[$key] = 0.0;
            foreach ($items as $item) {
                $amount = $this->conversion->convert((float) $item->remaining_amount, $item->currency_code, $user)['converted_amount'] ?? null;
                if ($amount === null) {
                    // No exchange rate: report separately instead of mixing currencies
                    $unconverted[] = ['type' => $type, 'currency' => $item->currency_code, 'outstanding' => (float) $item->remaining_amount];

                    continue;
                }
                $converted[$key] += (float) $amount;
            }
        }

        $summary['net_position'] = [
            'currency' => $currency,
            'owed_to_me' => round($converted['owed_to_me'], 2),
            'i_owe' => round($converted['i_owe'], 2),
            'net' => round($converted['owed_to_me'] - $converted['i_owe'], 2),
            'excluded_without_exchange_rate' => $unconverted,
        ];

        return $summary;
    }

    /** @return array<string, mixed> */
    public function createLoan(int $userId, array $data): array
    {
        return $this->create('loan', $userId, $data);
    }

    /** @return array<string, mixed> */
    public function createBorrowing(int $userId, array $data): array
    {
        return $this->create('borrowing', $userId, $data);
    }

    /** @return array<string, mixed> */
    public function recordLoanRepayment(int $userId, array $data): array
    {
        return $this->repay('loan', $userId, (int) $data['loan_id'], $data);
    }

    /** @return array<string, mixed> */
    public function recordBorrowingRepayment(int $userId, array $data): array
    {
        return $this->repay('borrowing', $userId, (int) $data['borrowing_id'], $data);
    }

    /** @return array<string, mixed> */
    public function writeOffLoan(int $userId, array $data): array
    {
        return $this->writeOff('loan', $userId, (int) $data['loan_id']);
    }

    /** @return array<string, mixed> */
    public function writeOffBorrowing(int $userId, array $data): array
    {
        return $this->writeOff('borrowing', $userId, (int) $data['borrowing_id']);
    }

    /** @return array<string, mixed> */
    private function list(string $type, int $userId, array $filters): array
    {
        $person = self::TYPES[$type]['person'];
        $limit = min(max((int) ($filters['limit'] ?? 20), 1), self::LIMIT_MAX);

        $query = $this->query($type, $userId)
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $status === 'outstanding'
                ? $query->outstanding()
                : $query->where('status', $status))
            ->when($filters['counterparty'] ?? null, fn (Builder $query, string $name) => $query->where($person, 'like', '%'.trim($name).'%'))
            ->when($filters['overdue'] ?? false, fn (Builder $query) => $query->outstanding()->whereNotNull('due_date')->whereDate('due_date', '<', today()));

        $total = (clone $query)->count();
        $items = $query->orderByDesc(self::TYPES[$type]['date'])->orderByDesc('id')->limit($limit)->get();

        return [
            'items' => $items->map(fn (Model $item) => $this->item($type, $item))->all(),
            'total' => $total,
            'truncated' => $total > $limit,
        ];
    }

    /** @return array<string, mixed> */
    private function create(string $type, int $userId, array $data): array
    {
        $meta = self::TYPES[$type];

        return DB::transaction(function () use ($type, $meta, $userId, $data): array {
            $account = $this->ownedAccount($userId, $data['account_id'] ?? null);
            $currency = $this->currencyFor($userId, $account, $data['currency_code'] ?? null);
            $date = $data[$meta['date']];
            $transaction = $account
                ? $this->recordTransaction($meta['opening'], $userId, $account, $data['amount'], $date, $data[$meta['person']], $data['category_id'] ?? null)
                : null;

            $payload = [
                'user_id' => $userId,
                'transaction_id' => $transaction?->id,
                $meta['person'] => $data[$meta['person']],
                'amount' => $data['amount'],
                'currency_code' => $currency,
                $meta['date'] => $date,
                'due_date' => $data['due_date'] ?? null,
                'message' => $data['message'] ?? null,
            ];

            $debt = $type === 'loan' ? $this->loans->createLoan($payload) : $this->borrowings->createBorrowing($payload);

            return $this->item($type, $debt->fresh()) + ['transaction_id' => $transaction?->id];
        });
    }

    /** @return array<string, mixed> */
    private function repay(string $type, int $userId, int $id, array $data): array
    {
        $meta = self::TYPES[$type];

        return DB::transaction(function () use ($type, $meta, $userId, $id, $data): array {
            $debt = $this->query($type, $userId)->findOrFail($id);
            $account = $this->ownedAccount($userId, $data['account_id'] ?? null);

            if ($account && $account->currency_code !== $debt->currency_code) {
                throw ValidationException::withMessages([
                    'account_id' => "The account currency ({$account->currency_code}) must match the debt currency ({$debt->currency_code}).",
                ]);
            }

            $repaymentData = [
                'amount' => $data['amount'],
                'repaid_at' => $data['repaid_at'] ?? now()->toDateString(),
                'note' => $data['note'] ?? null,
            ];
            $repayment = $type === 'loan'
                ? $this->loans->addRepayment($debt, $repaymentData)
                : $this->borrowings->addRepayment($debt, $repaymentData);

            $transaction = $account
                ? $this->recordTransaction($meta['repayment'], $userId, $account, $repayment->amount, $repaymentData['repaid_at'], $debt->{$meta['person']}, $data['category_id'] ?? null)
                : null;
            $transaction && $repayment->update(['transaction_id' => $transaction->id]);

            return [
                'repayment_id' => $repayment->id,
                'amount' => (float) $repayment->amount,
                'repaid_at' => $repaymentData['repaid_at'],
                'transaction_id' => $transaction?->id,
                $type => $this->item($type, $debt->fresh()),
            ];
        });
    }

    /** @return array<string, mixed> */
    private function writeOff(string $type, int $userId, int $id): array
    {
        return DB::transaction(function () use ($type, $userId, $id): array {
            $debt = $this->query($type, $userId)->lockForUpdate()->findOrFail($id);

            if (! in_array($debt->status, ['active', 'partially_repaid'], true)) {
                throw ValidationException::withMessages(['status' => 'Only an outstanding debt can be written off.']);
            }

            $debt->update(['status' => 'written_off']);

            return $this->item($type, $debt->fresh());
        });
    }

    private function query(string $type, int $userId): Builder
    {
        return self::TYPES[$type]['model']::query()->where('user_id', $userId);
    }

    private function ownedAccount(int $userId, mixed $accountId): ?Account
    {
        return $accountId ? Account::where('user_id', $userId)->findOrFail($accountId) : null;
    }

    private function currencyFor(int $userId, ?Account $account, ?string $requested): string
    {
        if ($account && $requested && $requested !== $account->currency_code) {
            throw ValidationException::withMessages([
                'currency_code' => "The currency must match the account currency ({$account->currency_code}).",
            ]);
        }

        return $account?->currency_code ?? $requested ?? $this->conversion->defaultCurrency(User::findOrFail($userId));
    }

    /** Ledger entry for money that actually moved; prefers the user's loan category when none is given. */
    private function recordTransaction(string $kind, int $userId, Account $account, float|int|string $amount, string $date, string $person, mixed $categoryId): Transaction
    {
        $category = $categoryId
            ? Category::userCategories($userId)->findOrFail($categoryId)
            : Category::userCategories($userId)->where('is_loan', true)->byType($kind)->where('is_active', true)
                ->orderByRaw('user_id IS NULL')->first();

        $data = [
            'user_id' => $userId,
            'account_id' => $account->id,
            'category_id' => $category?->id,
            'amount' => $amount,
            'description' => $person,
            'transaction_date' => $date,
        ];

        return $kind === 'income' ? $this->transactions->recordIncome($data) : $this->transactions->recordExpense($data);
    }

    /** @return array<string, mixed> */
    private function item(string $type, Model $debt): array
    {
        $meta = self::TYPES[$type];

        return [
            'id' => $debt->id,
            'type' => $type,
            'person' => $debt->{$meta['person']},
            'amount' => (float) $debt->amount,
            'repaid' => (float) $debt->amount_repaid,
            'remaining' => (float) $debt->remaining_amount,
            'currency' => $debt->currency_code,
            'date' => $debt->{$meta['date']}?->toDateString(),
            'due_date' => $debt->due_date?->toDateString(),
            'overdue' => $debt->due_date !== null && $debt->due_date->lt(today()) && in_array($debt->status, ['active', 'partially_repaid'], true),
            'status' => $debt->status,
            'note' => $debt->message,
        ];
    }
}
