<?php

declare(strict_types=1);

use App\Jobs\SyncFeedSource;
use App\Models\FeedSource;
use App\Models\Topic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

// The `view-admin` Gate checks $user->is_admin (confirmed).

// --- Access control -------------------------------------------------------

it('redirects guests away from the feed sources index', function () {
    $this->get(route('admin.feed-sources.index'))
        ->assertRedirect(); // to login
});

it('forbids non-admin users from the feed sources index', function () {
    $this->actingAs(actingAsRegularUser())
        ->get(route('admin.feed-sources.index'))
        ->assertNotFound();
});

it('allows admins to view the feed sources index', function () {
    $this->actingAs(actingAsAdmin())
        ->get(route('admin.feed-sources.index'))
        ->assertOk()
        ->assertViewIs('admin.feed-sources.index')
        ->assertViewHas('sources');
});

// --- index / create -------------------------------------------------------

it('lists feed sources with their topic and post count eager loaded', function () {
    $topic = Topic::factory()->create();
    FeedSource::factory()->for($topic, 'topic')->create(['provider' => 'tumblr', 'handle' => 'foodporn']);

    $response = $this->actingAs(actingAsAdmin())->get(route('admin.feed-sources.index'));

    $response->assertOk();
    $response->assertViewHas('sources', fn ($sources) => $sources->contains('handle', 'foodporn'));
});

it('shows the create form with providers and topics', function () {
    Topic::factory()->create(['name' => 'foodporn']);

    $this->actingAs(actingAsAdmin())
        ->get(route('admin.feed-sources.create'))
        ->assertOk()
        ->assertViewHas('providers', ['reddit', 'bluesky', 'tumblr'])
        ->assertViewHas('topics');
});

// --- store ------------------------------------------------------------

it('creates a feed source with an existing topic and clears the sources cache', function () {
    Cache::put(FeedSource::VISIBLE_CACHE_KEY, ['stale' => true]);
    $topic = Topic::factory()->create();

    $response = $this->actingAs(actingAsAdmin())->post(route('admin.feed-sources.store'), [
        'provider' => 'tumblr',
        'handle' => 'foodporn',
        'display_name' => 'Foodporn',
        'topic_id' => $topic->id,
    ]);

    $response->assertRedirect(route('admin.feed-sources.index'));
    $this->assertDatabaseHas('feed_sources', [
        'provider' => 'tumblr',
        'handle' => 'foodporn',
        'topic_id' => $topic->id,
    ]);
    expect(Cache::get(FeedSource::VISIBLE_CACHE_KEY))->toBeNull();
});

it('creates a feed source with a brand new topic typed in', function () {
    $response = $this->actingAs(actingAsAdmin())->post(route('admin.feed-sources.store'), [
        'provider' => 'bluesky',
        'handle' => 'catsofbluesky',
        'display_name' => 'Cats',
        'new_topic' => 'cats',
    ]);

    $response->assertRedirect(route('admin.feed-sources.index'));
    $topic = Topic::where('name', 'cats')->first();
    expect($topic)->not->toBeNull();
    $this->assertDatabaseHas('feed_sources', [
        'handle' => 'catsofbluesky',
        'topic_id' => $topic->id,
    ]);
});

it('rejects a feed source with neither topic_id nor new_topic', function () {
    $this->actingAs(actingAsAdmin())
        ->post(route('admin.feed-sources.store'), [
            'provider' => 'reddit',
            'handle' => 'memes',
            'display_name' => 'Memes',
        ])
        ->assertSessionHasErrors(['topic_id', 'new_topic']);
});

it('rejects a duplicate handle for the same provider', function () {
    FeedSource::factory()->create(['provider' => 'reddit', 'handle' => 'memes']);
    $topic = Topic::factory()->create();

    $this->actingAs(actingAsAdmin())
        ->post(route('admin.feed-sources.store'), [
            'provider' => 'reddit',
            'handle' => 'memes',
            'display_name' => 'Memes Again',
            'topic_id' => $topic->id,
        ])
        ->assertSessionHasErrors(['handle']);
});

it('allows the same handle across two different providers', function () {
    FeedSource::factory()->create(['provider' => 'reddit', 'handle' => 'memes']);
    $topic = Topic::factory()->create();

    $this->actingAs(actingAsAdmin())
        ->post(route('admin.feed-sources.store'), [
            'provider' => 'tumblr',
            'handle' => 'memes',
            'display_name' => 'Memes',
            'topic_id' => $topic->id,
        ])
        ->assertSessionDoesntHaveErrors('handle');
});

it('rejects an invalid provider', function () {
    $topic = Topic::factory()->create();

    $this->actingAs(actingAsAdmin())
        ->post(route('admin.feed-sources.store'), [
            'provider' => 'myspace',
            'handle' => 'whatever',
            'display_name' => 'Whatever',
            'topic_id' => $topic->id,
        ])
        ->assertSessionHasErrors(['provider']);
});

// --- edit / update ------------------------------------------------------

it('shows the edit form for a source', function () {
    $source = FeedSource::factory()->create();

    $this->actingAs(actingAsAdmin())
        ->get(route('admin.feed-sources.edit', $source))
        ->assertOk()
        ->assertViewHas('source', fn ($s) => $s->is($source));
});

it('updates a feed source and clears the sources cache', function () {
    Cache::put(FeedSource::VISIBLE_CACHE_KEY, ['stale' => true]);
    $source = FeedSource::factory()->create([
        'provider' => 'reddit',
        'display_name' => 'Old Name',
    ]);
    $topic = Topic::factory()->create();

    $response = $this->actingAs(actingAsAdmin())->put(route('admin.feed-sources.update', $source), [
        'provider' => $source->provider,
        'handle' => $source->handle,
        'display_name' => 'New Name',
        'topic_id' => $topic->id,
    ]);

    $response->assertRedirect(route('admin.feed-sources.index'));

    $this->assertDatabaseHas('feed_sources', ['id' => $source->id, 'display_name' => 'New Name']);
    expect(Cache::get(FeedSource::VISIBLE_CACHE_KEY))->toBeNull();
});

it('does not flag a validation error when updating a source without changing its own handle', function () {
    $source = FeedSource::factory()->create(['provider' => 'reddit', 'handle' => 'memes']);
    $topic = Topic::factory()->create();

    $this->actingAs(actingAsAdmin())
        ->put(route('admin.feed-sources.update', $source), [
            'provider' => 'reddit',
            'handle' => 'memes',
            'display_name' => 'Memes Updated',
            'topic_id' => $topic->id,
        ])
        ->assertSessionDoesntHaveErrors('handle');
});

// --- destroy ------------------------------------------------------------

it('deletes a feed source and clears the sources cache', function () {
    Cache::put(FeedSource::VISIBLE_CACHE_KEY, ['stale' => true]);
    $source = FeedSource::factory()->create();

    $response = $this->actingAs(actingAsAdmin())->delete(route('admin.feed-sources.destroy', $source));

    $response->assertRedirect(route('admin.feed-sources.index'));
    $this->assertDatabaseMissing('feed_sources', ['id' => $source->id]);
    expect(Cache::get(FeedSource::VISIBLE_CACHE_KEY))->toBeNull();
});

// --- toggle / toggleVisibility ------------------------------------------

it('toggles active state', function () {
    $source = FeedSource::factory()->create(['active' => true]);

    $this->actingAs(actingAsAdmin())
        ->patch(route('admin.feed-sources.toggle', $source))
        ->assertRedirect(route('admin.feed-sources.index'));

    expect($source->fresh()->active)->toBeFalse();
});

it('toggles visibility and clears the sources cache', function () {
    Cache::put(FeedSource::VISIBLE_CACHE_KEY, ['stale' => true]);
    $source = FeedSource::factory()->create(['visible' => false]);

    $this->actingAs(actingAsAdmin())
        ->patch(route('admin.feed-sources.toggle-visibility', $source))
        ->assertRedirect(route('admin.feed-sources.index'));

    expect($source->fresh()->visible)->toBe(1);
    expect(Cache::get(FeedSource::VISIBLE_CACHE_KEY))->toBeNull();
});

// --- sync ------------------------------------------------------------

it('dispatches a sync job for the source', function () {
    Queue::fake();
    $source = FeedSource::factory()->create();

    $this->actingAs(actingAsAdmin())
        ->post(route('admin.feed-sources.sync', $source))
        ->assertRedirect(route('admin.feed-sources.index'));

    Queue::assertPushed(SyncFeedSource::class);
});
