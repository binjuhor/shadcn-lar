<?php

namespace Modules\Finance\Services;

use Carbon\Carbon;
use Modules\Finance\Models\{Currency, Loan};

class FinancialLoanContextBuilder
{
    public function __construct(
        protected ExchangeRateService $exchangeRateService
    ) {}

    public function build(int $userId): string
    {
        $loans = Loan::query()
            ->without('media')
            ->with(['repayments' => fn ($query) => $query->orderByDesc('repaid_at')])
            ->where('user_id', $userId)
            ->orderByRaw("CASE WHEN status IN ('active', 'partially_repaid') THEN 0 WHEN status = 'repaid' THEN 1 ELSE 2 END")
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->orderByDesc('loan_date')
            ->get();

        if ($loans->isEmpty()) {
            return '';
        }

        $currency = $this->getDefaultCurrency();
        $today = now()->startOfDay();
        $rows = $loans->map(fn (Loan $loan) => $this->formatLoanRow($loan, $currency, $today));
        $collectibleRows = $rows->whereIn('status', ['active', 'partially_repaid']);
        $overdueRows = $collectibleRows->where('is_overdue', true);
        $upcomingRows = $collectibleRows
            ->where('is_overdue', false)
            ->filter(fn (array $row) => $row['due_date'] !== null)
            ->sortBy('due_date');
        $noDueDateRows = $collectibleRows->filter(fn (array $row) => $row['due_date'] === null);
        $repaidRows = $rows->where('status', 'repaid');
        $writtenOffRows = $rows->where('status', 'written_off');

        $totalLoaned = $rows
            ->where('status', '!=', 'written_off')
            ->sum('amount_default');
        $totalRepaid = $rows
            ->where('status', '!=', 'written_off')
            ->sum('amount_repaid_default');
        $totalOutstanding = $collectibleRows->sum('remaining_default');

        $lines = [
            '## Loan Receivables Report',
            "Currency: {$currency}",
            'Use this section for questions about money being loaned out, who owes money, how much is left to collect, overdue loans, and when money should come back.',
            'Total loaned (excluding written off): '.$this->roundMoney($totalLoaned)." {$currency}",
            'Total repaid: '.$this->roundMoney($totalRepaid)." {$currency}",
            'Total outstanding/expected back: '.$this->roundMoney($totalOutstanding)." {$currency}",
            "Outstanding loans count: {$collectibleRows->count()}",
        ];

        if ($overdueRows->isNotEmpty()) {
            $lines[] = 'Overdue loans:';
            foreach ($overdueRows->sortByDesc('days_overdue') as $row) {
                $lines[] = $this->loanLine($row, $currency);
            }
        }

        if ($upcomingRows->isNotEmpty()) {
            $lines[] = 'Upcoming expected repayments:';
            foreach ($upcomingRows as $row) {
                $lines[] = $this->loanLine($row, $currency);
            }
        }

        if ($noDueDateRows->isNotEmpty()) {
            $lines[] = 'Outstanding loans without due date:';
            foreach ($noDueDateRows->sortByDesc('remaining_default') as $row) {
                $lines[] = $this->loanLine($row, $currency);
            }
        }

        if ($repaidRows->isNotEmpty()) {
            $lines[] = 'Recently repaid loans:';
            foreach ($repaidRows->sortByDesc('last_repayment_date')->take(5) as $row) {
                $lines[] = $this->loanLine($row, $currency);
            }
        }

        if ($writtenOffRows->isNotEmpty()) {
            $lines[] = 'Written off loans:';
            foreach ($writtenOffRows as $row) {
                $lines[] = $this->loanLine($row, $currency);
            }
        }

        return implode("\n", $lines);
    }

    protected function formatLoanRow(Loan $loan, string $currency, Carbon $today): array
    {
        $amount = $this->convertToDefault((float) $loan->amount, $loan->currency_code, $currency);
        $amountRepaid = $this->convertToDefault((float) $loan->amount_repaid, $loan->currency_code, $currency);
        $remaining = max($amount - $amountRepaid, 0);
        $dueDate = $loan->due_date?->copy()->startOfDay();
        $isOutstanding = in_array($loan->status, ['active', 'partially_repaid'], true) && $remaining > 0;
        $isOverdue = $isOutstanding && $dueDate !== null && $dueDate->lt($today);

        return [
            'borrower_name' => $loan->borrower_name,
            'status' => $loan->status,
            'amount_default' => $this->roundMoney($amount),
            'amount_repaid_default' => $this->roundMoney($amountRepaid),
            'remaining_default' => $this->roundMoney($remaining),
            'currency_code' => $loan->currency_code,
            'amount_original' => $this->roundMoney((float) $loan->amount),
            'remaining_original' => $this->roundMoney((float) $loan->remaining_amount),
            'loan_date' => $loan->loan_date?->toDateString(),
            'due_date' => $dueDate?->toDateString(),
            'days_until_due' => $dueDate ? (int) $today->diffInDays($dueDate, false) : null,
            'days_overdue' => $isOverdue && $dueDate ? (int) $dueDate->diffInDays($today) : 0,
            'is_overdue' => $isOverdue,
            'progress_percent' => round($loan->progress_percent, 1),
            'last_repayment_date' => $loan->repayments->first()?->repaid_at?->toDateString(),
            'message' => $loan->message,
        ];
    }

    protected function loanLine(array $row, string $currency): string
    {
        $parts = [
            "- {$row['borrower_name']} ({$row['status']})",
            "loaned {$row['amount_default']} {$currency}",
            "repaid {$row['amount_repaid_default']} {$currency}",
            "remaining {$row['remaining_default']} {$currency}",
            "loan date {$row['loan_date']}",
        ];

        if ($row['currency_code'] !== $currency) {
            $parts[] = "original loan {$row['amount_original']} {$row['currency_code']}";
            $parts[] = "original remaining {$row['remaining_original']} {$row['currency_code']}";
        }

        if ($row['due_date']) {
            $parts[] = $row['is_overdue']
                ? "due {$row['due_date']} ({$row['days_overdue']} days overdue)"
                : "due {$row['due_date']} ({$row['days_until_due']} days left)";
        } else {
            $parts[] = 'no due date';
        }

        if ($row['last_repayment_date']) {
            $parts[] = "last repayment {$row['last_repayment_date']}";
        }

        if ($row['message']) {
            $parts[] = "note {$row['message']}";
        }

        return implode('; ', $parts);
    }

    protected function getDefaultCurrency(): string
    {
        $user = auth()->user();
        $userSettings = $user?->finance_settings ?? [];

        return $userSettings['default_currency'] ?? Currency::where('is_default', true)->first()?->code ?? 'VND';
    }

    protected function convertToDefault(float $amount, string $fromCurrency, string $defaultCurrency): float
    {
        if ($fromCurrency === $defaultCurrency) {
            return $amount;
        }

        try {
            return $this->exchangeRateService->convert($amount, $fromCurrency, $defaultCurrency);
        } catch (\Throwable) {
            return $amount;
        }
    }

    protected function roundMoney(float $amount): float
    {
        return round($amount, 2);
    }
}
