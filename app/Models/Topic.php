<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

#[Fillable(['name'])]
class Topic extends Model
{
    use HasFactory;

    public const string VISIBLE_CACHE_KEY = 'feed_visible_topics';

    public function sources(): HasMany
    {
        return $this->hasMany(FeedSource::class);
    }

    public static function cachedVisible(): Collection
    {
        return collect(Cache::remember(
            self::VISIBLE_CACHE_KEY,
            now()->addHour(),
            fn () => static::query()
                ->whereHas('sources', fn ($query) => $query->where('visible', true))
                ->orderBy('name')
                ->get()
                ->toArray()
        ));
    }
}
