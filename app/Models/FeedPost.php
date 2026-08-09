<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    protected $casts = [
        'posted_at' => 'datetime',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(FeedSource::class, 'feed_source_id');
    }
}
