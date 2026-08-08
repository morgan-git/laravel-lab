<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FeedPost;

class FeedSelector
{
    /**
     * Existing behavior — explicit provider + handle. Kept as-is so
     * anything already calling this (and its tests) keeps working.
     */
    public function random(string $provider, string $handle): ?FeedPost
    {
        return FeedPost::whereHas(
            'source',
            fn ($query) => $query->where('provider', $provider)->where('handle', $handle)
        )->inRandomOrder()->first();
    }

    /**
     * Provider-agnostic lookup by topic. Lets a caller (like the Discord
     * webhook) request "foodporn" without needing to know which provider
     * actually serves that topic, or what that provider's own handle
     * looks like (e.g. Bluesky's "food-porn.bsky.social" vs Tumblr's
     * "foodporn" tag). Respects `visible`, same rule the public feed
     * page follows.
     */
    public function randomForTopic(string $topic): ?FeedPost
    {
        return FeedPost::whereHas(
            'source',
            fn ($query) => $query->where('topic', $topic)->where('visible', true)
        )->inRandomOrder()->first();
    }
}
