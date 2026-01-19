<?php

namespace Modules\Finance\Services;

use Carbon\Carbon;
use Illuminate\Support\{Collection, Facades\DB};
use Modules\Finance\Models\{Currency, RecurringTransaction};

class RecurringTransactionService
{
    public function __construct(
        protected TransactionService $transactionService,
        protected ExchangeRateService $exchangeRateService
    ) {}

    public function create(array $data): RecurringTransaction
    {
        $startDate = Carbon::parse($data['start_date']);

        // Calculate initial next_run_date based on frequency
        $nextRunDate = $this->calculateInitialNextRun($data, $startDate);

        return RecurringTransaction::create([
            'user_id' => auth()->id(),
            'account_id' => $data['account_id'],
            'category_id' => $data['category_id'] ?? null,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'transaction_type' => $data['transaction_type'],
            'amount' => $data['amount'],
            'currency_code' => $data['currency_code'],
            'frequency' => $data['frequency'],
            'day_of_week' => $data['day_of_week'] ?? null,
            'day_of_month' => $data['day_of_month'] ?? null,
            'month_of_year' => $data['month_of_year'] ?? null,
            'start_date' => $startDate,
            'end_date' => isset($data['end_date']) ? Carbon::parse($data['end_date']) : null,
            'next_run_date' => $nextRunDate,
            'is_active' => $data['is_active'] ?? true,
            'auto_create' => $data['auto_create'] ?? true,
        ]);
    }

    public function update(RecurringTransaction $recurring, array $data): RecurringTransaction
    {
        $updateData = [];

        // Handle simple fields
        $simpleFields = ['account_id', 'name', 'transaction_type', 'amount', 'currency_code', 'is_active', 'auto_create'];
        foreach ($simpleFields as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null) {
                $updateData[$field] = $data[$field];
            }
        }

        // Handle nullable fields (allow setting to null)
        $nullableFields = ['category_id', 'description', 'day_of_week', 'day_of_month', 'month_of_year'];
        foreach ($nullableFields as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = $data[$field];
            }
        }

        // Handle frequency
        if (array_key_exists('frequency', $data)) {
            $updateData['frequency'] = $data['frequency'];
        }

        // Handle date fields
        if (array_key_exists('start_date', $data) && $data['start_date']) {
            $updateData['start_date'] = Carbon::parse($data['start_date']);
        }

        if (array_key_exists('end_date', $data)) {
            $updateData['end_date'] = $data['end_date'] ? Carbon::parse($data['end_date']) : null;
        }

        // Check if schedule-related fields changed - recalculate next_run_date
        $scheduleFields = ['frequency', 'day_of_week', 'day_of_month', 'month_of_year', 'start_date'];
        $scheduleChanged = false;
        foreach ($scheduleFields as $field) {
            if (array_key_exists($field, $data)) {
                $oldValue = $recurring->$field;
                $newValue = $data[$field];
                if ($oldValue != $newValue) {
                    $scheduleChanged = true;
                    break;
                }
            }
        }

        if ($scheduleChanged) {
            $mergedData = array_merge($recurring->toArray(), $data);
            $startDate = isset($data['start_date']) ? Carbon::parse($data['start_date']) : $recurring->start_date;
            $updateData['next_run_date'] = $this->calculateInitialNextRun($mergedData, $startDate);
        }

        $recurring->update($updateData);

        return $recurring->fresh();
    }

    public function processDue(): array
    {
        $dueRecurrings = RecurringTransaction::due()
            ->with(['account', 'category'])
            ->get();

        $processed = [];
        $errors = [];

        foreach ($dueRecurrings as $recurring) {
            try {
                if ($recurring->auto_create) {
                    $this->generateTransaction($recurring);
                }
                $processed[] = $recurring->id;
            } catch (\Exception $e) {
                $errors[$recurring->id] = $e->getMessage();
            }
        }

        return [
            'processed' => count($processed),
            'errors' => $errors,
        ];
    }

    public function generateTransaction(RecurringTransaction $recurring): void
    {
        DB::transaction(function () use ($recurring) {
            $transactionData = [
                'account_id' => $recurring->account_id,
                'category_id' => $recurring->category_id,
                'amount' => $recurring->amount,
                'description' => $recurring->name,
                'transaction_date' => $recurring->next_run_date->format('Y-m-d'),
            ];

            if ($recurring->transaction_type === 'income') {
                $this->transactionService->recordIncome($transactionData);
            } else {
                $this->transactionService->recordExpense($transactionData);
            }

            // Update recurring transaction
            $recurring->update([
                'last_run_date' => $recurring->next_run_date,
                'next_run_date' => $recurring->calculateNextRunDate(),
            ]);
        });
    }

    public function pause(RecurringTransaction $recurring): RecurringTransaction
    {
        $recurring->update(['is_active' => false]);

        return $recurring;
    }

    public function resume(RecurringTransaction $recurring): RecurringTransaction
    {
        // Recalculate next run date if it's in the past
        $nextRun = $recurring->next_run_date;
        while ($nextRun < now()) {
            $nextRun = $recurring->calculateNextRunDate($nextRun);
        }

        $recurring->update([
            'is_active' => true,
            'next_run_date' => $nextRun,
        ]);

        return $recurring;
    }

    public function getUpcoming(int $userId, int $days = 30): Collection
    {
        return RecurringTransaction::forUser($userId)
            ->upcoming($days)
            ->with(['account', 'category'])
            ->orderBy('next_run_date')
            ->get();
    }

    public function getPreview(RecurringTransaction $recurring, int $count = 12): array
    {
        $previews = [];
        $date = $recurring->next_run_date->copy();

        for ($i = 0; $i < $count; $i++) {
            if ($recurring->end_date && $date > $recurring->end_date) {
                break;
            }

            $previews[] = [
                'date' => $date->format('Y-m-d'),
                'amount' => $recurring->amount,
                'type' => $recurring->transaction_type,
            ];

            $date = $recurring->calculateNextRunDate($date);
        }

        return $previews;
    }

    public function getMonthlyProjection(int $userId, ?string $currencyCode = null): array
    {
        $defaultCode = $currencyCode ?? Currency::where('is_default', true)->first()?->code ?? 'VND';

        $recurrings = RecurringTransaction::forUser($userId)
            ->active()
            ->with(['category', 'account'])
            ->get();

        $income = 0;
        $expense = 0;
        $passiveIncome = 0;

        foreach ($recurrings as $recurring) {
            $monthlyAmount = $recurring->monthly_amount;

            // Convert to default currency if different
            if ($recurring->currency_code !== $defaultCode) {
                $monthlyAmount = $this->convertToDefault(
                    $monthlyAmount,
                    $recurring->currency_code,
                    $defaultCode,
                    $recurring->account?->rate_source
                );
            }

            if ($recurring->transaction_type === 'income') {
                $income += $monthlyAmount;
                if ($recurring->category?->is_passive) {
                    $passiveIncome += $monthlyAmount;
                }
            } else {
                $expense += $monthlyAmount;
            }
        }

        return [
            'monthly_income' => (int) $income,
            'monthly_expense' => (int) $expense,
            'monthly_passive_income' => (int) $passiveIncome,
            'monthly_net' => (int) ($income - $expense),
            'passive_coverage' => $expense > 0 ? round(($passiveIncome / $expense) * 100, 1) : 0,
            'currency_code' => $defaultCode,
        ];
    }

    protected function convertToDefault(float $amount, string $fromCurrency, string $defaultCurrency, ?string $source = null): float
    {
        if ($fromCurrency === $defaultCurrency) {
            return $amount;
        }

        try {
            return $this->exchangeRateService->convert($amount, $fromCurrency, $defaultCurrency, $source);
        } catch (\Exception) {
            return $amount;
        }
    }

    protected function calculateInitialNextRun(array $data, Carbon $startDate): Carbon
    {
        $frequency = $data['frequency'];
        $today = now()->startOfDay();

        // If start date is in the future, use it
        if ($startDate > $today) {
            return $startDate;
        }

        // Calculate next occurrence from today
        $nextRun = $startDate->copy();

        while ($nextRun <= $today) {
            $nextRun = match ($frequency) {
                'daily' => $nextRun->addDay(),
                'weekly' => $nextRun->addWeek(),
                'monthly' => $this->addMonth($nextRun, $data['day_of_month'] ?? $startDate->day),
                'yearly' => $nextRun->addYear(),
                default => $nextRun->addMonth(),
            };
        }

        return $nextRun;
    }

    protected function addMonth(Carbon $date, int $dayOfMonth): Carbon
    {
        $next = $date->copy()->addMonth();
        $maxDay = $next->daysInMonth;
        $targetDay = min($dayOfMonth, $maxDay);

        return $next->setDay($targetDay);
    }
}
