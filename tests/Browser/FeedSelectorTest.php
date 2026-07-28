<?php

use App\Models\FeedSource;
use App\Services\FeedSelector;

it('returns null when no posts exist for the given provider and handle', function () {
    // Source exists, but has zero posts attached to it.
    FeedSource::factory()->create([
        'provider' => 'reddit',
        'handle' => 'memes',
        'active' => true,
    ]);

    $selector = new FeedSelector();

    expect($selector->random('reddit', 'memes'))->toBeNull();
});

it('returns null when no matching source exists at all', function () {
    $selector = new FeedSelector();

    expect($selector->random('reddit', 'nonexistent_subreddit'))->toBeNull();
});
