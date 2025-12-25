<?php

namespace Modules\Notification\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Notification\Database\Factories\NotificationTemplateFactory;
use Modules\Notification\Enums\NotificationCategory;
use Modules\Notification\Enums\NotificationChannel;

class NotificationTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'subject',
        'body',
        'category',
        'channels',
        'variables',
        'is_active',
    ];

    protected $casts = [
        'category' => NotificationCategory::class,
        'channels' => 'array',
        'variables' => 'array',
        'is_active' => 'boolean',
    ];

    protected static function newFactory(): NotificationTemplateFactory
    {
        return NotificationTemplateFactory::new();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory(Builder $query, NotificationCategory|string $category): Builder
    {
        $value = $category instanceof NotificationCategory ? $category->value : $category;

        return $query->where('category', $value);
    }

    public function scopeByChannel(Builder $query, NotificationChannel|string $channel): Builder
    {
        $value = $channel instanceof NotificationChannel ? $channel->value : $channel;

        return $query->whereJsonContains('channels', $value);
    }

    public function render(array $data = []): array
    {
        $subject = $this->subject;
        $body = $this->body;

        foreach ($data as $key => $value) {
            $placeholder = "{{ $key }}";
            $subject = str_replace($placeholder, $value, $subject);
            $body = str_replace($placeholder, $value, $body);
        }

        return [
            'subject' => $subject,
            'body' => $body,
        ];
    }

    public function supportsChannel(NotificationChannel|string $channel): bool
    {
        $value = $channel instanceof NotificationChannel ? $channel->value : $channel;

        return in_array($value, $this->channels ?? []);
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $template) {
            if (empty($template->slug)) {
                $template->slug = self::generateUniqueSlug($template->name);
            }
        });

        static::updating(function (self $template) {
            if ($template->isDirty('slug') && ! empty($template->slug)) {
                $template->slug = self::generateUniqueSlug($template->slug, $template->id);
            }
        });
    }

    private static function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $slug = \Str::slug($name);
        $originalSlug = $slug;
        $count = 1;

        while (self::slugExists($slug, $ignoreId)) {
            $slug = "{$originalSlug}-{$count}";
            $count++;
        }

        return $slug;
    }

    private static function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        $query = self::where('slug', $slug);

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }
}
