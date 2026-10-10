<?php

use App\Contracts\FeedProvider;
use App\Jobs\SyncFeedSource;
use App\Models\FeedSource;
use App\Models\Topic;

beforeEach(function () {
    $this->source = FeedSource::create([
        'provider' => 'tumblr',
        'handle' => 'foodporn',
        'display_name' => 'Food Porn',
        'topic_id' => Topic::firstOrCreate(['name' => 'Food'])->id,
        'active' => true,
        'visible' => true,
    ]);

    // Swaps the real Tumblr provider for one that returns exactly $posts.
    $this->fakeProvider = function (array $posts): void {
        $provider = Mockery::mock(FeedProvider::class);
        $provider->shouldReceive('fetch')->andReturn(collect($posts));

        $this->instance(FeedProvider::class.':tumblr', $provider);
    };

    // A post shaped like TumblrService::fetch() output.
    $this->makePost = fn (string $id): array => [
        'id' => $id,
        'title' => "Post {$id}",
        'url' => "https://example.com/post/{$id}",
        'author' => 'testblog',
        'image' => "https://example.com/img/{$id}.jpg",
        'content' => "Content for post {$id}",
        'updated' => now()->toDateTimeString(),
        'dedupe_key' => md5($id),
    ];
});

it('stamps last_fetched_at when a run only finds posts it already has', function () {
    ($this->fakeProvider)([($this->makePost)('1001')]);

    // First run saves the post.
    new SyncFeedSource($this->source)->handle();

    $this->source->update(['last_fetched_at' => now()->subDay()]);

    $this->freezeTime();

    // Second run sees the same post, so nothing new is created.
    new SyncFeedSource($this->source->fresh())->handle();

    expect($this->source->fresh()->last_fetched_at->toDateTimeString())
        ->toBe(now()->toDateTimeString());
});

it('leaves last_fetched_at alone when the provider returns nothing', function () {
    ($this->fakeProvider)([]);

    $before = now()->subDay();
    $this->source->update(['last_fetched_at' => $before]);

    new SyncFeedSource($this->source->fresh())->handle();

    expect($this->source->fresh()->last_fetched_at->toDateTimeString())
        ->toBe($before->toDateTimeString());
});

it('does not stamp an inactive source', function () {
    ($this->fakeProvider)([($this->makePost)('1001')]);

    $this->source->update(['active' => false]);

    new SyncFeedSource($this->source->fresh())->handle();

    expect($this->source->fresh()->last_fetched_at)->toBeNull();
});
