<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

#[Fillable([
    'provider',
    'handle',
    'display_name',
    'topic_id',
    'active',
    'visible',
    'last_fetched_at',
])]
class FeedSource extends Model
{
    use HasFactory;

    public const string VISIBLE_CACHE_KEY = 'feed_visible_sources';

    protected $casts = [
        'active' => 'boolean',
        'last_fetched_at' => 'datetime',
    ];

    public function posts(): HasMany
    {
        return $this->hasMany(FeedPost::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeForProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    /**
     * Visible feed sources, cached for an hour since this list barely
     * changes. Single source of truth used by FeedController (public feed
     * dropdown), the nav's Feeds dropdown, and invalidated from
     * FeedSourceController whenever an admin changes a source.
     */
    public static function cachedVisible(): Collection
    {
        return collect(Cache::remember(
            self::VISIBLE_CACHE_KEY,
            now()->addHour(),
            fn () => static::where('visible', true)
                ->with('topic')
                ->withCount('posts')
                ->orderBy('provider')
                ->orderBy('handle')
                ->get()
                ->toArray()
        ));

    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }
}
