<?php

namespace Modules\Finance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\{
    Factories\HasFactory,
    Model,
    Relations\BelongsTo,
    Relations\HasMany
};
use Kalnoy\Nestedset\NodeTrait;

class Category extends Model
{
    use HasFactory;
    use NodeTrait;

    protected $table = 'finance_categories';

    protected $fillable = [
        'parent_id',
        'user_id',
        'name',
        'name_key',
        'type',
        'icon',
        'color',
        'is_active',
        'is_passive',
        'expense_type',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_passive' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    public function scopeUserCategories($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('user_id', $userId)
                ->orWhereNull('user_id');
        });
    }

    public function scopeByType($query, string $type)
    {
        return $query->whereIn('type', [$type, 'both']);
    }

    protected static function newFactory()
    {
        return \Modules\Finance\Database\Factories\CategoryFactory::new();
    }
}
