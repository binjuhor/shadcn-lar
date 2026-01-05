<?php

namespace Modules\Finance\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecurringTransaction extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'finance_recurring_transactions';

    protected $fillable = [
        'user_id',
        'account_id',
        'category_id',
        'name',
        'description',
        'transaction_type',
        'amount',
        'currency_code',
        'frequency',
        'day_of_week',
        'day_of_month',
        'month_of_year',
        'start_date',
        'end_date',
        'next_run_date',
        'last_run_date',
        'is_active',
        'auto_create',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'day_of_week' => 'integer',
            'day_of_month' => 'integer',
            'month_of_year' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'next_run_date' => 'date',
            'last_run_date' => 'date',
            'is_active' => 'boolean',
            'auto_create' => 'boolean',
        ];
    }

    protected $appends = ['monthly_amount', 'yearly_amount'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDue($query)
    {
        return $query->where('is_active', true)
            ->where('next_run_date', '<=', now()->toDateString())
            ->where(function ($q) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', now()->toDateString());
            });
    }

    public function scopeUpcoming($query, int $days = 30)
    {
        return $query->where('is_active', true)
            ->where('next_run_date', '<=', now()->addDays($days)->toDateString())
            ->where(function ($q) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', now()->toDateString());
            });
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function isDue(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->end_date && $this->end_date < now()) {
            return false;
        }

        return $this->next_run_date <= now()->toDateString();
    }

    public function calculateNextRunDate(?Carbon $fromDate = null): Carbon
    {
        $from = $fromDate ?? ($this->next_run_date ?? Carbon::parse($this->start_date));

        return match ($this->frequency) {
            'daily' => $from->copy()->addDay(),
            'weekly' => $from->copy()->addWeek(),
            'monthly' => $this->calculateNextMonthlyDate($from),
            'yearly' => $this->calculateNextYearlyDate($from),
            default => $from->copy()->addMonth(),
        };
    }

    protected function calculateNextMonthlyDate(Carbon $from): Carbon
    {
        $next = $from->copy()->addMonth();
        $dayOfMonth = $this->day_of_month ?? $from->day;

        // Handle months with fewer days
        $maxDay = $next->daysInMonth;
        $targetDay = min($dayOfMonth, $maxDay);

        return $next->setDay($targetDay);
    }

    protected function calculateNextYearlyDate(Carbon $from): Carbon
    {
        $next = $from->copy()->addYear();
        $monthOfYear = $this->month_of_year ?? $from->month;
        $dayOfMonth = $this->day_of_month ?? $from->day;

        $next->setMonth($monthOfYear);
        $maxDay = $next->daysInMonth;
        $targetDay = min($dayOfMonth, $maxDay);

        return $next->setDay($targetDay);
    }

    public function getMonthlyAmountAttribute(): int
    {
        return match ($this->frequency) {
            'daily' => $this->amount * 30,
            'weekly' => (int) round($this->amount * 4.33),
            'monthly' => $this->amount,
            'yearly' => (int) round($this->amount / 12),
            default => $this->amount,
        };
    }

    public function getYearlyAmountAttribute(): int
    {
        return match ($this->frequency) {
            'daily' => $this->amount * 365,
            'weekly' => $this->amount * 52,
            'monthly' => $this->amount * 12,
            'yearly' => $this->amount,
            default => $this->amount * 12,
        };
    }

    public function getFrequencyLabelAttribute(): string
    {
        return match ($this->frequency) {
            'daily' => 'Daily',
            'weekly' => 'Weekly',
            'monthly' => 'Monthly',
            'yearly' => 'Yearly',
            default => ucfirst($this->frequency),
        };
    }
}
