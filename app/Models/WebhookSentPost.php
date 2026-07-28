<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'provider',
    'requester_id',
    'feed_post_id',
    'sent_at',
])]
#[WithoutTimestamps]
class WebhookSentPost extends Model
{
    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(FeedPost::class, 'feed_post_id');
    }
}
