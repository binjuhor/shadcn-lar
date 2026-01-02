<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExchangeRate extends Model
{
    protected $table = 'finance_exchange_rates';

    protected $fillable = [
        'base_currency',
        'target_currency',
        'rate',
        'bid_rate',
        'ask_rate',
        'source',
        'rate_date',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'float',
            'bid_rate' => 'float',
            'ask_rate' => 'float',
            'rate_date' => 'datetime',
        ];
    }

    public function baseCurrencyInfo(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'base_currency', 'code');
    }

    public function targetCurrencyInfo(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'target_currency', 'code');
    }

    public function scopeLatest($query)
    {
        return $query->orderBy('rate_date', 'desc');
    }

    public function scopeForPair($query, string $base, string $target)
    {
        return $query->where('base_currency', $base)->where('target_currency', $target);
    }

    public function scopeFromSource($query, string $source)
    {
        return $query->where('source', $source);
    }

    public static function getLatestRate(string $base, string $target, ?string $source = null): ?self
    {
        $query = static::forPair($base, $target)->latest();

        if ($source) {
            $query->fromSource($source);
        }

        return $query->first();
    }

    public function convert(float $amount): float
    {
        return $amount * (float) $this->rate;
    }

    public function toArray(): array
    {
        $array = parent::toArray();

        // Add camelCase relationship keys for frontend
        if ($this->relationLoaded('baseCurrencyInfo')) {
            $array['baseCurrency'] = $this->getRelation('baseCurrencyInfo')?->toArray();
        }

        if ($this->relationLoaded('targetCurrencyInfo')) {
            $array['targetCurrency'] = $this->getRelation('targetCurrencyInfo')?->toArray();
        }

        // Remove snake_case relationship keys that Laravel adds
        unset($array['base_currency_info'], $array['target_currency_info']);

        return $array;
    }
}
