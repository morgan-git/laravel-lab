<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * There is a currently-open Laravel 13 framework bug
 * (laravel/framework#59270) where the #[Fillable] attribute is not
 * reliably picked up by the static ::create() method, throwing
 * MassAssignmentException even when correctly declared. Keeping the
 * attribute here for consistency with the rest of the app's style, but
 * also declaring the property explicitly as a defensive fallback that
 * works regardless of whether that upstream bug is present in whatever
 * exact Laravel patch version this app is running. Safe to remove the
 * property once that issue is confirmed fixed upstream, if you want to
 * fully commit to the attribute-only style at that point.
 */
#[Fillable([
    'provider',
    'requester_id',
    'feed_post_id',
    'sent_at',
])]
#[WithoutTimestamps]
class WebhookSentPost extends Model
{
    protected $fillable = [
        'provider',
        'requester_id',
        'feed_post_id',
        'sent_at',
    ];

    /**
     * Same defensive belt-and-suspenders reasoning as $fillable above —
     * this table genuinely has no created_at/updated_at columns, and
     * this property guarantees that's respected even if
     * #[WithoutTimestamps] has an analogous attribute-parsing issue.
     */
    public $timestamps = false;

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(FeedPost::class, 'feed_post_id');
    }
}
