# Adding a Feed Provider

This application supports multiple feed providers through the `FeedProvider` contract.

The goal is to keep provider-specific API/feed handling isolated inside a service while the rest of the application works with a consistent post structure.

A Discord user or the public site should not need to know how a provider represents a feed. For example, a topic such as `foodporn` might be backed by a Tumblr tag, a Bluesky feed handle, or another provider-specific identifier.

## 1. Create the provider service

Create a service in:

```text
app/Services/
```

The service should implement `App\Contracts\FeedProvider`:

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\FeedProvider;
use Illuminate\Support\Collection;

class ExampleService implements FeedProvider
{
    public function fetch(string $handle): Collection
    {
        // Fetch and normalize the provider's posts here.

        return collect();
    }
}
```

The contract is intentionally small:

```php
interface FeedProvider
{
    public function fetch(string $handle): Collection;
}
```

A provider receives its provider-specific `handle` and returns a collection of normalized posts.

## 2. Normalize provider data

The rest of the application should not need to understand the provider's response format.

Each returned post should use the application's normalized structure:

```php
[
    'id' => 'provider-specific-id',
    'title' => 'Post title',
    'url' => 'https://example.com/post',
    'author' => 'author-name',
    'updated' => '2026-08-08T12:00:00+00:00',
    'content' => 'Post content',
    'image' => 'https://example.com/image.jpg',
]
```

Provider-specific parsing, filtering, cleanup, and API quirks belong inside the provider service.

If your provider is prone to cross-posting the same content across multiple accounts (a content-farm pattern — see `TumblrService`), you can also include a `dedupe_key` in each normalized post. `SyncFeedSource` will use it to skip saving a duplicate when the same key has already been seen for that source. This field is optional — omit it entirely if your provider doesn't have this problem.

## 3. Register the provider

Register the service in `AppServiceProvider` using the provider name stored in `FeedSource`:

```php
$this->app->bind(FeedProvider::class . ':example', ExampleService::class);
```

A plain class-string binding like this is enough — Laravel's container will auto-resolve the service's constructor dependencies (see `RedditService`'s optional `?Client $client` constructor param for an example of a dependency that's still resolvable this way, while also remaining swappable for a mock in tests).

The provider name should match the value stored in the `provider` column.

For example:

```text
provider: tumblr
handle: foodporn
```

The `handle` is provider-specific and lives directly on the `FeedSource` row. Which **topic** that source belongs to is a separate concept — see step 6.

## 4. Add the provider to the admin

Add the provider name to the available providers in:

```text
app/Http/Controllers/Admin/FeedSourceController.php
```

For example:

```php
private const array AVAILABLE_PROVIDERS = [
    'reddit',
    'bluesky',
    'tumblr',
    'example',
];
```

The admin feed-source manager can then create sources for the provider.

A feed source has several distinct pieces of information:

| Field          | Purpose                                                            |
| -------------- | ------------------------------------------------------------------ |
| `provider`     | Which service handles the feed                                     |
| `handle`       | Provider-specific feed identifier                                  |
| `display_name` | Human-readable name shown by the site                              |
| `topic_id`     | Foreign key to the `Topic` this source belongs to                  |
| `active`       | Whether the source should continue syncing                         |
| `visible`      | Whether the source is exposed to the public site / topic selection |

`topic_id` is managed through the admin form as either picking an existing `Topic` from a dropdown, or typing a new one — `FeedSourceController` handles finding-or-creating the `Topic` row behind the scenes (`resolveTopicId()`), so you never need to create a `Topic` separately before adding a source for it.

Keeping `active` and `visible` separate is intentional.

Pausing a source does not necessarily mean that its existing posts need to disappear from the database.

## 5. Syncing

Feed sources are synchronized through the existing `SyncFeedSource` job.

The job resolves the provider from the source:

```php
$provider = app(
    FeedProvider::class . ':' . $this->source->provider
);

$posts = $provider->fetch($this->source->handle);
```

This means the job does not need provider-specific logic.

Adding another provider therefore does not require changing the synchronization job.

Note that `SyncFeedSource` does **not** filter posts by any last-synced timestamp — every post a provider returns gets processed on every run. This is intentional: a post's own timestamp can lag behind when it actually surfaces in a provider's feed, so filtering by a previous sync's cutoff can permanently drop a post that was never actually saved. `updateOrCreate()` (keyed on `feed_source_id` + `external_id`) already makes reprocessing the same posts safe and cheap, so there's no need for a provider service to do its own "only return new posts" filtering either — return everything the API gives you and let the job's existing idempotency handle the rest.

## 6. Topics

A topic represents what the application wants to retrieve, rather than how a particular provider represents it. Topics are their own model (`App\Models\Topic`), not a free-text field on `FeedSource`.

For example:

```text
Topic (name: "foodporn")
  ├─ FeedSource: provider = tumblr,   handle = foodporn
  └─ FeedSource: provider = bluesky,  handle = food-porn.bsky.social
```

The Discord webhook can therefore request:

```text
foodporn
```

without knowing which provider supplies that topic or what its provider-specific handle looks like.

Topic selection is handled by `FeedSelector`:

```php
public function randomForTopic(string $topic): ?FeedPost
{
    return FeedPost::whereHas(
        'source',
        fn ($query) => $query
            ->whereHas('topic', fn ($topicQuery) => $topicQuery->where('name', $topic))
            ->where('visible', true)
    )->inRandomOrder()->first();
}
```

This keeps provider details out of the consumer-facing command.

## 7. Provider-specific filtering

Provider services are also responsible for filtering content when necessary.

Examples include:

* malformed provider responses
* moderator/system posts
* spam
* unwanted content
* unsupported languages
* provider-specific metadata
* missing images or other required fields

Those decisions should generally stay inside the provider service rather than leaking into `SyncFeedSource`.

## 8. Testing

Add provider-specific tests under:

```text
tests/Unit/
```

Provider services in this codebase are tested with a mocked HTTP client (see `RedditService`'s tests for the `makeService(string $fixtureFile)` / `MockHandler` pattern) — no real network calls, no database. That makes `tests/Unit/` the right home: no DB, no HTTP, pure logic.

Tests should verify that the provider:

1. can retrieve its source;
2. correctly normalizes posts;
3. handles malformed or unexpected provider content;
4. filters content that the provider service is responsible for filtering.

The webhook should be tested separately at the application level, in `tests/Feature/` — it should care about topics and normalized `FeedPost` records, not the provider's API format. See `tests/Feature/WebhookControllerTest.php` for the current reference pattern (real signed HTTP requests, real DB assertions).


## 9. Environment variables

Not every provider requires credentials.

If a provider exposes public feeds that can be retrieved without authentication, the service may not need a new environment variable.

If authentication or an API key is required, add the credential to `.env.example` and read it through Laravel's configuration system rather than accessing `env()` throughout the service.

For example:

```php
// config/services.php

'example' => [
    'token' => env('EXAMPLE_API_TOKEN'),
],
```

Then:

```php
config('services.example.token')
```

can be used by the service.

Never commit actual credentials to the repository.

## Summary

Adding a provider should generally require:

1. Create a service implementing `FeedProvider`.
2. Normalize its responses into the application's post structure.
3. Register the provider in `AppServiceProvider`.
4. Add the provider to the admin's accepted provider list.
5. Add any required configuration/environment variables.
6. Add provider-specific tests under `tests/Unit/`.
7. Create feed sources through the admin with the appropriate provider, handle, display name, and topic — the topic can be picked from existing ones or created inline.

The synchronization job, public feed UI, and topic-based Discord selection should not need to know the provider's API details.
