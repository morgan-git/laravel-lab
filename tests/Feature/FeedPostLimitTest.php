<?php

use App\Models\FeedPost;
use App\Models\FeedSource;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;

// FeedController::POST_LIMIT is private, so the expected cap is repeated
// here. Update this if the constant changes.
const FEED_PAGE_POST_LIMIT = 200;

beforeEach(function () {
    $this->source = FeedSource::factory()->create([
        'provider' => 'tumblr',
        'handle' => 'foodporn',
        'active' => true,
        'visible' => true,
        'topic_id' => Topic::factory(),
    ]);

    // Post 000 is the newest, and each following post is one minute older.
    $this->makePosts = function (int $count) {
        FeedPost::factory()
            ->count($count)
            ->sequence(fn (Sequence $sequence) => [
                'feed_source_id' => $this->source->id,
                'external_id' => 'ext-'.$sequence->index,
                'title' => sprintf('Limit test post %03d', $sequence->index),
                'dedupe_key' => md5('limit-test-'.$sequence->index),
                'posted_at' => now()->subMinutes($sequence->index),
            ])
            ->create();
    };
});

it('loads only the newest posts when the table has more than the limit', function () {
    ($this->makePosts)(FEED_PAGE_POST_LIMIT + 50);

    $this->actingAs(User::factory()->create())
        ->get(route('feed.index'))
        ->assertOk()
        ->assertViewHas('posts', fn ($posts) => $posts->count() === FEED_PAGE_POST_LIMIT
            && $posts->first()['title'] === 'Limit test post 000'
            && $posts->last()['title'] === sprintf('Limit test post %03d', FEED_PAGE_POST_LIMIT - 1));
});

it('does not trim the page when there are fewer posts than the limit', function () {
    ($this->makePosts)(5);

    $this->actingAs(User::factory()->create())
        ->get(route('feed.index'))
        ->assertOk()
        ->assertViewHas('posts', fn ($posts) => $posts->count() === 5);
});
