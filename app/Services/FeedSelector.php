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
     *
     * $provider and $requesterId are optional so this stays backward
     * compatible with any caller that doesn't care about has-seen
     * exclusion — if either is omitted, exclusion is simply skipped.
     */
    public function randomForTopic(string $topicName, ?string $provider = null, ?string $requesterId = null): ?FeedPost
    {
        $visibleForTopic = fn () => FeedPost::whereHas(
            'source',
            fn ($query) => $query->where('visible', true)
                ->whereHas('topic', fn ($topicQuery) => $topicQuery->where('name', $topicName))
        );

        if ($provider === null || $requesterId === null) {
            return $visibleForTopic()->inRandomOrder()->first();
        }

        $unseen = $visibleForTopic()
            ->whereNotIn('id', function ($query) use ($provider, $requesterId) {
                $query->select('feed_post_id')
                    ->from('webhook_sent_posts')
                    ->where('provider', $provider)
                    ->where('requester_id', $requesterId);
            })
            ->inRandomOrder()
            ->first();

        if ($unseen) {
            return $unseen;
        }

        // Every visible post for this topic has already been sent to
        // this requester. Rather than returning null here — which the
        // controller reports as "No posts found for {topic}", misleading
        // since posts genuinely exist, they've just all been seen — fall
        // back to the full pool so the command keeps working (with
        // repeats) instead of going permanently dead for small topics.
        // PruneSeenWebhookPosts is what keeps this fallback rare rather
        // than the normal case.
        return $visibleForTopic()->inRandomOrder()->first();
    }
}
