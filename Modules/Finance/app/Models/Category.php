<?php

namespace Modules\Finance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\{
    Casts\Attribute,
    Factories\HasFactory,
    Model,
    Relations\BelongsTo,
    Relations\HasMany
};
use Kalnoy\Nestedset\NodeTrait;
use Modules\Finance\Database\Factories\CategoryFactory;

class Category extends Model
{
    use HasFactory;
    use NodeTrait;

    protected $table = 'finance_categories';

    /**
     * Allowed icon keys (kebab-case Lucide names), shared by web and mobile clients.
     * A subcategory with a null icon inherits its parent's (see display_icon).
     * Must match CATEGORY_ICONS in resources/js/components/category-icon.tsx.
     */
    public const ICONS = [
        'wallet', 'briefcase', 'trending-up', 'plus-circle', 'piggy-bank', 'landmark', 'banknote', 'coins',
        'hand-coins', 'credit-card', 'percent', 'calculator', 'receipt', 'gem', 'utensils', 'coffee',
        'pizza', 'apple', 'beer', 'shopping-bag', 'shopping-cart', 'shirt', 'tag', 'package',
        'home', 'building-2', 'sofa', 'zap', 'droplet', 'flame', 'wifi', 'smartphone',
        'tv', 'wrench', 'hammer', 'repeat', 'car', 'fuel', 'bus', 'bike',
        'plane', 'hotel', 'heart', 'stethoscope', 'pill', 'dumbbell', 'scissors', 'sparkles',
        'shield', 'umbrella', 'users', 'baby', 'book', 'graduation-cap', 'laptop', 'dog',
        'cat', 'paw-print', 'film', 'music', 'gamepad-2', 'ticket', 'gift', 'hand-heart',
        'more-horizontal',
    ];

    public const DEFAULT_ICON = 'more-horizontal';

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
        'is_loan',
        'expense_type',
    ];

    /**
     * Parent icons are batch-loaded so display_icon never triggers per-row queries.
     */
    protected $with = ['iconParent'];

    protected $hidden = ['iconParent'];

    protected $appends = ['display_icon'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_passive' => 'boolean',
            'is_loan' => 'boolean',
        ];
    }

    /**
     * Minimal parent lookup used only to resolve inherited icons.
     */
    public function iconParent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id')
            ->select(['id', 'icon'])
            ->without('iconParent');
    }

    /**
     * Icon to render: the category's own icon, or its parent's when a subcategory has none.
     */
    protected function displayIcon(): Attribute
    {
        return Attribute::get(fn () => $this->icon ?? $this->iconParent?->icon);
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
        return CategoryFactory::new();
    }
}
