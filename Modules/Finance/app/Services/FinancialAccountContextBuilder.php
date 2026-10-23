<?php

namespace Modules\Finance\Services;

use Modules\Finance\Models\{Account, Currency};

class FinancialAccountContextBuilder
{
    public function __construct(
        protected ExchangeRateService $exchangeRateService
    ) {}

    public function build(int $userId): string
    {
        $accounts = Account::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        if ($accounts->isEmpty()) {
            return '';
        }

        $currency = $this->getDefaultCurrency();
        $rows = $accounts->map(fn (Account $account) => $this->formatAccountRow($account, $currency));
        $includedRows = $rows->where('excluded', false);
        $assetRows = $includedRows->where('is_debt_account', false);
        $debtRows = $rows->where('debt_amount', '>', 0);
        $richestAccount = $assetRows
            ->filter(fn (array $row) => $row['balance_default'] > 0)
            ->sortByDesc('balance_default')
            ->first();

        $totalIncludedBalance = $includedRows->sum('balance_default');
        $totalAssets = $assetRows->filter(fn (array $row) => $row['balance_default'] > 0)->sum('balance_default');
        $totalDebt = $debtRows->sum('debt_amount');
        $netAccountPosition = $totalAssets - $totalDebt;

        $lines = [
            '## Account Balance and Debt Report',
            "Currency: {$currency}",
            'Use this section for questions about account totals, account balances, largest account, cash/assets, credit cards, loans, or debt.',
            'Total included account balance: '.$this->roundMoney($totalIncludedBalance)." {$currency}",
            'Total positive assets: '.$this->roundMoney($totalAssets)." {$currency}",
            'Total debt/liabilities: '.$this->roundMoney($totalDebt)." {$currency}",
            'Net account position: '.$this->roundMoney($netAccountPosition)." {$currency}",
        ];

        if ($richestAccount) {
            $lines[] = "Account with most money: {$richestAccount['name']} ({$richestAccount['type']}): {$richestAccount['balance_default']} {$currency}";
        }

        $lines[] = 'Included active accounts:';
        foreach ($includedRows->sortByDesc('balance_default') as $row) {
            $lines[] = $this->accountLine($row, $currency);
        }

        if ($debtRows->isNotEmpty()) {
            $lines[] = 'Debt accounts:';
            foreach ($debtRows->sortByDesc('debt_amount') as $row) {
                $lines[] = "- {$row['name']} ({$row['type']}): debt {$row['debt_amount']} {$currency}; current balance {$row['balance_default']} {$currency}";
            }
        }

        $excludedRows = $rows->where('excluded', true);
        if ($excludedRows->isNotEmpty()) {
            $lines[] = 'Excluded from account totals:';
            foreach ($excludedRows as $row) {
                $lines[] = $this->accountLine($row, $currency);
            }
        }

        return implode("\n", $lines);
    }

    protected function formatAccountRow(Account $account, string $currency): array
    {
        $balance = $this->convertToDefault((float) $account->current_balance, $account->currency_code, $currency, $account->rate_source);
        $debtAmount = $this->debtAmount($account, $currency);

        return [
            'name' => $account->name,
            'type' => $account->account_type,
            'institution' => $account->institution_name,
            'currency' => $account->currency_code,
            'balance_original' => $this->roundMoney((float) $account->current_balance),
            'balance_default' => $this->roundMoney($balance),
            'initial_balance_default' => $this->roundMoney($this->convertToDefault((float) $account->initial_balance, $account->currency_code, $currency, $account->rate_source)),
            'debt_amount' => $this->roundMoney($debtAmount),
            'is_debt_account' => in_array($account->account_type, ['credit_card', 'loan'], true),
            'excluded' => (bool) $account->exclude_from_total,
        ];
    }

    protected function accountLine(array $row, string $currency): string
    {
        $parts = [
            "- {$row['name']} ({$row['type']})",
            "balance {$row['balance_default']} {$currency}",
        ];

        if ($row['currency'] !== $currency) {
            $parts[] = "original {$row['balance_original']} {$row['currency']}";
        }

        if ($row['institution']) {
            $parts[] = "institution {$row['institution']}";
        }

        if ($row['debt_amount'] > 0) {
            $parts[] = "debt {$row['debt_amount']} {$currency}";
        }

        return implode('; ', $parts);
    }

    protected function debtAmount(Account $account, string $currency): float
    {
        if (! in_array($account->account_type, ['credit_card', 'loan'], true)) {
            return 0;
        }

        $amount = $account->has_credit_limit
            ? $account->amount_owed
            : max(0, -1 * (float) $account->current_balance);

        if ($amount <= 0 && (float) $account->initial_balance > (float) $account->current_balance) {
            $amount = (float) $account->initial_balance - (float) $account->current_balance;
        }

        return $this->convertToDefault($amount, $account->currency_code, $currency, $account->rate_source);
    }

    protected function getDefaultCurrency(): string
    {
        $user = auth()->user();
        $userSettings = $user?->finance_settings ?? [];

        return $userSettings['default_currency'] ?? Currency::where('is_default', true)->first()?->code ?? 'VND';
    }

    protected function convertToDefault(float $amount, string $fromCurrency, string $defaultCurrency, ?string $source = null): float
    {
        if ($fromCurrency === $defaultCurrency) {
            return $amount;
        }

        try {
            return $this->exchangeRateService->convert($amount, $fromCurrency, $defaultCurrency, $source);
        } catch (\Throwable) {
            return $amount;
        }
    }

    protected function roundMoney(float $amount): float
    {
        return round($amount, 2);
    }
}
