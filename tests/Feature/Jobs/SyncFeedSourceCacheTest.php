<?php

use App\Contracts\FeedProvider;
use App\Jobs\SyncFeedSource;
use App\Models\FeedSource;
use App\Models\Topic;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->source = FeedSource::create([
        'provider' => 'tumblr',
        'handle' => 'foodporn',
        'display_name' => 'Food Porn',
        'topic_id' => Topic::firstOrCreate(['name' => 'Food'])->id,
        'active' => true,
        'visible' => true,
    ]);

    // Every cache key the feed page can use for a page that includes this source.
    $this->sourceCacheKeys = [
        'feed_posts_all_all',
        'feed_posts_tumblr_all',
        'feed_posts_tumblr_foodporn',
        'feed_posts_topic_Food',
    ];

    $this->fakeProvider = function (array $posts): void {
        $provider = Mockery::mock(FeedProvider::class);
        $provider->shouldReceive('fetch')->andReturn(collect($posts));

        $this->instance(FeedProvider::class.':tumblr', $provider);
    };

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

    $this->seedCaches = function (): void {
        foreach ($this->sourceCacheKeys as $key) {
            Cache::put($key, ['stale'], now()->addMinutes(30));
        }

        // A page that should never include this source.
        Cache::put('feed_posts_reddit_all', ['untouched'], now()->addMinutes(30));
    };
});

it('builds feed cache keys in the format the feed page already uses', function () {
    expect(FeedSource::postsCacheKey())->toBe('feed_posts_all_all')
        ->and(FeedSource::postsCacheKey('tumblr'))->toBe('feed_posts_tumblr_all')
        ->and(FeedSource::postsCacheKey('tumblr', 'foodporn'))->toBe('feed_posts_tumblr_foodporn')
        ->and(FeedSource::postsCacheKey(topic: 'Food'))->toBe('feed_posts_topic_Food');
});

it('clears every feed page cache that includes the source when new posts arrive', function () {
    ($this->seedCaches)();
    ($this->fakeProvider)([($this->makePost)('1001')]);

    new SyncFeedSource($this->source)->handle();

    foreach ($this->sourceCacheKeys as $key) {
        expect(Cache::has($key))->toBeFalse("{$key} should have been cleared");
    }

    expect(Cache::has('feed_posts_reddit_all'))->toBeTrue();
});

it('leaves the caches alone when a run creates nothing new', function () {
    ($this->fakeProvider)([($this->makePost)('1001')]);

    // First run saves the post.
    new SyncFeedSource($this->source)->handle();

    ($this->seedCaches)();

    // Second run sees only the post it already has.
    new SyncFeedSource($this->source->fresh())->handle();

    foreach ($this->sourceCacheKeys as $key) {
        expect(Cache::has($key))->toBeTrue("{$key} should not have been cleared");
    }
});
