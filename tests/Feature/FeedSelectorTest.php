<?php

use App\Models\FeedPost;
use App\Models\FeedSource;
use App\Models\Topic;
use App\Models\WebhookSentPost;
use App\Services\FeedSelector;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// --- random() ------------------------------------------------------

it('returns null when no posts exist for the given provider and handle', function () {
    // Source exists, but has zero posts attached to it.
    FeedSource::factory()->create([
        'provider' => 'reddit',
        'handle' => 'memes',
        'active' => true,
    ]);

    $selector = new FeedSelector;

    expect($selector->random('reddit', 'memes'))->toBeNull();
});

it('returns null when no matching source exists at all', function () {
    $selector = new FeedSelector;

    expect($selector->random('reddit', 'nonexistent_subreddit'))->toBeNull();
});

// --- randomForTopic() — no coverage existed for this at all before -------

it('returns a visible post matching the topic', function () {
    $topic = Topic::factory()->create(['name' => 'foodporn']);
    $source = FeedSource::factory()->for($topic, 'topic')->create(['visible' => true]);
    $post = FeedPost::factory()->for($source, 'source')->create();

    $selector = new FeedSelector;

    expect($selector->randomForTopic('foodporn')->id)->toBe($post->id);
});

it('returns null when the topic has no source at all', function () {
    $selector = new FeedSelector;

    expect($selector->randomForTopic('not-a-real-topic'))->toBeNull();
});

it('does not return posts from a source marked not visible', function () {
    $topic = Topic::factory()->create(['name' => 'foodporn']);
    $source = FeedSource::factory()->for($topic, 'topic')->create(['visible' => false]);
    FeedPost::factory()->for($source, 'source')->create();

    $selector = new FeedSelector;

    expect($selector->randomForTopic('foodporn'))->toBeNull();
});

// --- randomForTopic() has-seen exclusion --------------------------------

it('excludes a post already sent to this provider and requester', function () {
    $topic = Topic::factory()->create(['name' => 'foodporn']);
    $source = FeedSource::factory()->for($topic, 'topic')->create(['visible' => true]);
    $seen = FeedPost::factory()->for($source, 'source')->create();
    $unseen = FeedPost::factory()->for($source, 'source')->create();

    WebhookSentPost::create([
        'provider' => 'discord',
        'requester_id' => 'guild-123',
        'feed_post_id' => $seen->id,
        'sent_at' => now(),
    ]);

    $result = (new FeedSelector)->randomForTopic('foodporn', 'discord', 'guild-123');

    expect($result->id)->toBe($unseen->id);
});

it('does not exclude a post seen by a different requester', function () {
    $topic = Topic::factory()->create(['name' => 'foodporn']);
    $source = FeedSource::factory()->for($topic, 'topic')->create(['visible' => true]);
    $post = FeedPost::factory()->for($source, 'source')->create();

    // Seen by a different guild — shouldn't affect guild-123's pool.
    WebhookSentPost::create([
        'provider' => 'discord',
        'requester_id' => 'guild-999',
        'feed_post_id' => $post->id,
        'sent_at' => now(),
    ]);

    $result = (new FeedSelector)->randomForTopic('foodporn', 'discord', 'guild-123');

    expect($result->id)->toBe($post->id);
});

it('does not exclude a post seen under a different provider', function () {
    $topic = Topic::factory()->create(['name' => 'foodporn']);
    $source = FeedSource::factory()->for($topic, 'topic')->create(['visible' => true]);
    $post = FeedPost::factory()->for($source, 'source')->create();

    WebhookSentPost::create([
        'provider' => 'slack', // hypothetically — not discord
        'requester_id' => 'guild-123',
        'feed_post_id' => $post->id,
        'sent_at' => now(),
    ]);

    $result = (new FeedSelector)->randomForTopic('foodporn', 'discord', 'guild-123');

    expect($result->id)->toBe($post->id);
});

it('falls back to repeating posts once every post for the topic has been seen', function () {
    $topic = Topic::factory()->create(['name' => 'foodporn']);
    $source = FeedSource::factory()->for($topic, 'topic')->create(['visible' => true]);
    $post = FeedPost::factory()->for($source, 'source')->create();

    WebhookSentPost::create([
        'provider' => 'discord',
        'requester_id' => 'guild-123',
        'feed_post_id' => $post->id,
        'sent_at' => now(),
    ]);

    // Everything for this topic has been seen by this requester — should
    // fall back to the full pool rather than returning null.
    $result = (new FeedSelector)->randomForTopic('foodporn', 'discord', 'guild-123');

    expect($result->id)->toBe($post->id);
});

it('ignores webhook_sent_posts entirely when provider/requesterId are omitted', function () {
    $topic = Topic::factory()->create(['name' => 'foodporn']);
    $source = FeedSource::factory()->for($topic, 'topic')->create(['visible' => true]);
    $post = FeedPost::factory()->for($source, 'source')->create();

    WebhookSentPost::create([
        'provider' => 'discord',
        'requester_id' => 'guild-123',
        'feed_post_id' => $post->id,
        'sent_at' => now(),
    ]);

    // No provider/requesterId passed — backward-compatible call shape,
    // should behave exactly as it did before has-seen existed.
    $result = (new FeedSelector)->randomForTopic('foodporn');

    expect($result->id)->toBe($post->id);
});
