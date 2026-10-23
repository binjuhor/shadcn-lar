<?php

namespace Modules\Finance\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\Transaction;

/**
 * Resolves the most likely account for a Smart Input transaction.
 *
 * Detection is deterministic and signal-ordered so the same input always maps to
 * the same account. Stronger, explicit evidence (an account number written in the
 * text) wins over softer signals (the account habitually used for similar entries).
 */
class AccountDetectionService
{
    public const REASON_ACCOUNT_NUMBER = 'account_number';

    public const REASON_HINT = 'hint';

    public const REASON_NAME = 'name';

    public const REASON_HISTORY_DESCRIPTION = 'history_description';

    public const REASON_HISTORY_CATEGORY = 'history_category';

    public const REASON_CONFIGURED_DEFAULT = 'configured_default';

    public const REASON_HISTORY_FREQUENT = 'history_frequent';

    public const REASON_DEFAULT_PAYMENT = 'default_payment';

    public const REASON_FALLBACK = 'fallback';

    /**
     * How many recent transactions to inspect when learning account habits.
     */
    private const HISTORY_WINDOW = 300;

    /**
     * Minimum number of digits required before an account number can be matched
     * inside free text. Shorter runs collide with amounts and dates too easily.
     */
    private const MIN_ACCOUNT_NUMBER_DIGITS = 4;

    /**
     * Common words that carry no signal when comparing transaction descriptions.
     */
    private const DESCRIPTION_STOPWORDS = [
        'the', 'and', 'for', 'with', 'payment', 'transfer', 'bank', 'card',
        'thanh', 'toan', 'chuyen', 'khoan', 'giao', 'dich', 'mua', 'ban',
        'tien', 'phi', 'hoa', 'don', 'cho', 'cua', 'va', 'cac',
    ];

    public function detect(
        int $userId,
        ?string $rawText = null,
        ?string $accountHint = null,
        ?string $description = null,
        ?int $categoryId = null,
    ): ?array {
        $accounts = $this->activeAccounts($userId);

        if ($accounts->isEmpty()) {
            return null;
        }

        if ($rawText && ($match = $this->matchAccountNumber($rawText, $accounts))) {
            return $match;
        }

        if ($accountHint && ($match = $this->matchHint($accountHint, $userId, $accounts))) {
            return $match;
        }

        if ($rawText && ($match = $this->matchText($rawText, $accounts))) {
            return $match;
        }

        if ($match = $this->matchHistory($userId, $description, $categoryId, $accounts)) {
            return $match;
        }

        if ($match = $this->configuredDefault($userId, $accounts)) {
            return $match;
        }

        if ($match = $this->mostFrequentlyUsed($userId, $accounts)) {
            return $match;
        }

        if ($match = $this->defaultPayment($userId, $accounts)) {
            return $match;
        }

        return $this->toResult($accounts->first(), self::REASON_FALLBACK);
    }

    /**
     * Match an explicit account hint produced by the AI parser or typed by the user.
     * Returns null instead of falling back to an arbitrary account.
     */
    public function matchHint(string $hint, int $userId, ?Collection $accounts = null): ?array
    {
        $accounts ??= $this->activeAccounts($userId);
        $hint = trim($hint);

        if ($hint === '' || $accounts->isEmpty()) {
            return null;
        }

        $hintLower = mb_strtolower($hint);

        foreach ($accounts as $account) {
            if (mb_strtolower($account->name) === $hintLower) {
                return $this->toResult($account, self::REASON_HINT);
            }
        }

        foreach ($accounts as $account) {
            $nameLower = mb_strtolower($account->name);

            if (str_contains($nameLower, $hintLower) || str_contains($hintLower, $nameLower)) {
                return $this->toResult($account, self::REASON_HINT);
            }
        }

        foreach ($accounts as $account) {
            if (! $account->institution_name) {
                continue;
            }

            $institutionLower = mb_strtolower($account->institution_name);

            if (str_contains($institutionLower, $hintLower) || str_contains($hintLower, $institutionLower)) {
                return $this->toResult($account, self::REASON_HINT);
            }
        }

        if ($match = $this->matchAccountNumber($hint, $accounts)) {
            return $match;
        }

        $typeMap = [
            'tiền mặt' => 'cash',
            'cash' => 'cash',
            'thẻ' => 'credit_card',
            'card' => 'credit_card',
            'ngân hàng' => 'bank',
            'bank' => 'bank',
            'ví' => 'e_wallet',
            'wallet' => 'e_wallet',
            'momo' => 'e_wallet',
            'zalopay' => 'e_wallet',
        ];

        foreach ($typeMap as $keyword => $type) {
            if (str_contains($hintLower, $keyword)) {
                $match = $accounts->firstWhere('account_type', $type);

                if ($match) {
                    return $this->toResult($match, self::REASON_HINT);
                }
            }
        }

        return null;
    }

    /**
     * Scan free text for a stored account number. Matches the full number or its
     * trailing digits, bounded by non-digits so amounts cannot be mistaken for it.
     */
    public function matchAccountNumber(string $text, Collection $accounts): ?array
    {
        $best = null;
        $bestLength = 0;

        foreach ($accounts as $account) {
            if (! $account->account_number) {
                continue;
            }

            $digits = preg_replace('/\D/', '', (string) $account->account_number);

            if (strlen($digits) < self::MIN_ACCOUNT_NUMBER_DIGITS) {
                continue;
            }

            // Prefer the longest stored sequence found in the text. Checking the full
            // number first then progressively shorter suffixes keeps "12345678" from
            // being matched by an account whose number merely ends in "5678".
            $candidates = [$digits];

            foreach ([6, 4] as $suffixLength) {
                if (strlen($digits) > $suffixLength) {
                    $candidates[] = substr($digits, -$suffixLength);
                }
            }

            foreach ($candidates as $candidate) {
                $length = strlen($candidate);

                if ($length <= $bestLength) {
                    continue;
                }

                if (preg_match('/(?<!\d)'.preg_quote($candidate, '/').'(?!\d)/', $text) === 1) {
                    $best = $account;
                    $bestLength = $length;
                    break;
                }
            }
        }

        return $best ? $this->toResult($best, self::REASON_ACCOUNT_NUMBER) : null;
    }

    /**
     * Scan free text for an account or institution name (e.g. "Techcombank").
     */
    public function matchText(string $text, Collection $accounts): ?array
    {
        $textLower = mb_strtolower($text);

        $best = null;
        $bestLength = 0;

        foreach ($accounts as $account) {
            foreach ([$account->institution_name, $account->name] as $label) {
                if (! $label) {
                    continue;
                }

                $labelLower = mb_strtolower(trim($label));
                $length = mb_strlen($labelLower);

                if ($length < 3 || $length <= $bestLength) {
                    continue;
                }

                if (str_contains($textLower, $labelLower)) {
                    $best = $account;
                    $bestLength = $length;
                }
            }
        }

        return $best ? $this->toResult($best, self::REASON_NAME) : null;
    }

    /**
     * Learn from the user's own history: the account used most often for a similar
     * description, then for the same category.
     */
    public function matchHistory(
        int $userId,
        ?string $description,
        ?int $categoryId,
        ?Collection $accounts = null,
    ): ?array {
        $accounts ??= $this->activeAccounts($userId);

        if ($accounts->isEmpty()) {
            return null;
        }

        $recent = Transaction::where('user_id', $userId)
            ->whereNotNull('account_id')
            ->latest('transaction_date')
            ->latest('id')
            ->limit(self::HISTORY_WINDOW)
            ->get(['account_id', 'category_id', 'description']);

        if ($recent->isEmpty()) {
            return null;
        }

        $activeIds = $accounts->pluck('id')->all();

        if ($description && ($match = $this->matchByDescription($recent, $description, $accounts, $activeIds))) {
            return $match;
        }

        if ($categoryId && ($match = $this->matchByCategory($recent, $categoryId, $accounts, $activeIds))) {
            return $match;
        }

        return null;
    }

    /**
     * The account the user reaches for most often, used as a soft habit signal.
     */
    public function mostFrequentlyUsed(int $userId, ?Collection $accounts = null): ?array
    {
        $accounts ??= $this->activeAccounts($userId);

        if ($accounts->isEmpty()) {
            return null;
        }

        $accountId = Transaction::where('user_id', $userId)
            ->whereIn('account_id', $accounts->pluck('id'))
            ->latest('transaction_date')
            ->latest('id')
            ->limit(self::HISTORY_WINDOW)
            ->get(['account_id'])
            ->countBy('account_id')
            ->sortDesc()
            ->keys()
            ->first();

        $account = $accountId ? $accounts->firstWhere('id', $accountId) : null;

        return $account ? $this->toResult($account, self::REASON_HISTORY_FREQUENT) : null;
    }

    /**
     * The account explicitly chosen in Settings → Smart Input.
     */
    public function configuredDefault(int $userId, ?Collection $accounts = null): ?array
    {
        $accounts ??= $this->activeAccounts($userId);

        $defaultAccountId = User::find($userId)?->finance_settings['default_smart_input_account_id'] ?? null;

        if (! $defaultAccountId) {
            return null;
        }

        $account = $accounts->firstWhere('id', (int) $defaultAccountId);

        return $account ? $this->toResult($account, self::REASON_CONFIGURED_DEFAULT) : null;
    }

    /**
     * The user's default payment account.
     */
    public function defaultPayment(int $userId, ?Collection $accounts = null): ?array
    {
        $accounts ??= $this->activeAccounts($userId);

        $account = $accounts->firstWhere('is_default_payment', true);

        return $account ? $this->toResult($account, self::REASON_DEFAULT_PAYMENT) : null;
    }

    /**
     * The account that has been suggested for this description before.
     */
    private function matchByDescription(
        Collection $recent,
        string $description,
        Collection $accounts,
        array $activeIds,
    ): ?array {
        $tokens = $this->descriptionTokens($description);

        if (empty($tokens)) {
            return null;
        }

        $scores = [];

        foreach ($recent as $transaction) {
            if (! $transaction->description) {
                continue;
            }

            $descriptionLower = mb_strtolower($transaction->description);
            $hits = 0;

            foreach ($tokens as $token) {
                if (str_contains($descriptionLower, $token)) {
                    $hits++;
                }
            }

            if ($hits === 0) {
                continue;
            }

            $scores[$transaction->account_id] = ($scores[$transaction->account_id] ?? 0) + $hits;
        }

        return $this->bestScoredAccount($scores, $accounts, $activeIds, self::REASON_HISTORY_DESCRIPTION);
    }

    private function matchByCategory(
        Collection $recent,
        int $categoryId,
        Collection $accounts,
        array $activeIds,
    ): ?array {
        $scores = [];

        foreach ($recent as $transaction) {
            if ((int) $transaction->category_id !== $categoryId) {
                continue;
            }

            $scores[$transaction->account_id] = ($scores[$transaction->account_id] ?? 0) + 1;
        }

        return $this->bestScoredAccount($scores, $accounts, $activeIds, self::REASON_HISTORY_CATEGORY);
    }

    private function bestScoredAccount(
        array $scores,
        Collection $accounts,
        array $activeIds,
        string $reason,
    ): ?array {
        arsort($scores);

        foreach (array_keys($scores) as $accountId) {
            if (! in_array($accountId, $activeIds, true)) {
                continue;
            }

            $account = $accounts->firstWhere('id', $accountId);

            if ($account) {
                return $this->toResult($account, $reason);
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function descriptionTokens(string $description): array
    {
        return collect(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($description), -1, PREG_SPLIT_NO_EMPTY))
            ->filter(fn (string $token) => mb_strlen($token) >= 3)
            ->reject(fn (string $token) => in_array($token, self::DESCRIPTION_STOPWORDS, true))
            ->unique()
            ->values()
            ->all();
    }

    private function activeAccounts(int $userId): Collection
    {
        return Account::where('user_id', $userId)
            ->where('is_active', true)
            ->orderByRaw("CASE WHEN currency_code = 'VND' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get();
    }

    private function toResult(Account $account, string $reason): array
    {
        return [
            'id' => $account->id,
            'name' => $account->name,
            'reason' => $reason,
        ];
    }
}
