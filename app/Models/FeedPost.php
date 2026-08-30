<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * See laravel/framework#59270. Keeping #[Fillable] for consistency with
 * the rest of the app, but declaring $fillable explicitly too as a
 * defensive fallback, same reasoning as WebhookSentPost. Safe to drop
 * the property once that upstream bug is confirmed fixed.
 */
#[Fillable([
    'feed_source_id',
    'external_id',
    'title',
    'url',
    'author',
    'image_url',
    'content',
    'posted_at',
    'dedupe_key',
])]
class FeedPost extends Model
{
    use HasFactory;

    protected $fillable = [
        'feed_source_id',
        'external_id',
        'title',
        'url',
        'author',
        'image_url',
        'content',
        'posted_at',
        'dedupe_key',
    ];

    protected $casts = [
        'posted_at' => 'datetime',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(FeedSource::class, 'feed_source_id');
    }
}
