# afterthesyntax

[![Run Pest Tests](https://github.com/morgan-git/laravel-lab/actions/workflows/tests.yml/badge.svg)](https://github.com/morgan-git/laravel-lab/actions/workflows/tests.yml)
- Larastan (PHPStan, level 5) for static analysis, Rector and Pint for formatting

A content aggregator that pulls posts from Tumblr and Bluesky, organizes them by topic, and serves them two ways: a public web feed, and a Discord bot whose slash commands return a random post from a topic. Built with Laravel 13 and PHP 8.4.

<!-- TODO: screenshot or GIF of the public feed. Save it as docs/images/feed.png, then replace this comment with: ![Public feed](docs/images/feed.png) -->

<!-- TODO: screenshot of a Discord slash command response. Save it as docs/images/discord.png, then replace this comment with: ![Discord bot](docs/images/discord.png) -->

<!-- TODO: screenshot of the admin dashboard. Save it as docs/images/admin.png, then replace this comment with: ![Admin dashboard](docs/images/admin.png) -->

Reddit support exists but is currently parked (see [Known limitations](#known-limitations)).

## What it does

- **Ingests** content from multiple providers on a schedule, normalized into a common shape regardless of source.
- **Organizes by topic** rather than by raw provider/handle. A `Topic` like "foodporn" can be backed by any number of Tumblr tags, Bluesky accounts, etc., and the web feed and Discord bot never need to know which.
- **Serves via a public web feed** at `/feed`, browsable by topic.
- **Serves via Discord slash commands** (`/foodporn`, `/cooking`, etc.), each registered per topic, returning a random post as a rich embed. A "has seen" table keeps the bot from repeating posts for the same requester.
- **Admin dashboard** for managing feed sources (add, edit, pause, toggle visibility, manually trigger a sync), users (admin access toggle), the job queue (view pending and failed jobs, retry or cancel), a webhook request log (sortable and filterable, with the payload in and out for each request), and an in-app viewer for these docs.

## Design decisions

- **Contracts plus tagged bindings.** Adding a new content source or a new outbound consumer means one class and one registration line. Controllers and jobs don't change. See [`docs/adding-a-provider.md`](docs/adding-a-provider.md) and [`docs/adding-a-consumer.md`](docs/adding-a-consumer.md).
- **Testable without the network.** Providers take an optional HTTP client (and `TumblrService` an optional text filter) through the constructor, so tests inject a mocked client and never touch a real API.
- **Idempotent sync.** `SyncFeedSource` uses `updateOrCreate` keyed on source and external ID, plus a normalized `dedupe_key` that catches the same content cross-posted under different IDs, so re-processing a feed is always safe.
- **Deeper Tumblr coverage.** Tumblr returns 20 posts per request, so the service walks back through up to five pages with the `before` parameter. See [`docs/tumblr-pagination.md`](docs/tumblr-pagination.md).
- **Shared text filtering.** `PostTextFilter` holds the script and language checks. Each provider passes in its own spam patterns.
- **One place builds cache keys.** The feed page and the sync job both use `FeedSource::postsCacheKey()`, so cache invalidation after a sync can't drift out of step with what the page caches.
- **Failures are logged without leaking secrets.** A failed Tumblr request is logged to a dedicated `feed-sync` channel with the status and exception class, never the message, because Guzzle's messages include the request URL and the API key in it.
- **Webhook safety.** Discord request signatures are verified (this needs PHP's `sodium` extension), and the endpoint is rate limited.

## Stack

- Laravel 13, PHP 8.4
- Pest (with `pest-plugin-browser`, see [Testing](#testing) for a currently known gap)
- Redis (via `predis`, **not** `phpredis`, see [Known limitations](#known-limitations)) for caching
- SQLite for local development, MySQL planned for production
- Database-driven queue (`QUEUE_CONNECTION=database`)
- DaisyUI, Tailwind, Alpine.js, Vite
- Laravel Herd for local dev

## Local setup

```bash
git clone https://github.com/morgan-git/laravel-lab.git
cd laravel-lab
composer setup   # installs deps, copies .env, generates app key, migrates, builds assets
```

Then, in a separate terminal, start everything with:

```bash
composer dev
```

or, if you're using the project's own process helper:

```bash
./scripts/process-up.sh
```

Pass `-r` / `--restart` to force-restart anything already running. **Note:** this script currently only watches for `queue:listen`, not `queue:work`. If you ever manually start a worker with `queue:work` instead, the script won't see it. Kill it by hand before switching back. This has genuinely caused a full day of "why is nothing updating" debugging before, so it's worth not repeating.

You'll also need:

- **Redis** running locally via Homebrew (`brew services start redis`)
- The pre-commit hook (`scripts/pre-commit`) is installed into `.git/hooks` automatically by `composer install`. It runs formatting, static analysis and the test suite before each commit.

### Environment variables

Beyond the standard Laravel `.env` values, this project needs:

```
DISCORD_PUBLIC_KEY=   # from the Discord Developer Portal, see docs/discord-commands.md
DISCORD_BOT_TOKEN=    # only needed for one-off slash command registration, not runtime
```

### Seeding feed sources

Feed sources (which provider and handle back which topic) are managed through the admin dashboard at `/admin/feed-sources`. No need to seed via `tinker`.

## Architecture

Two provider-style contracts drive most of the extensibility here:

- **`App\Contracts\FeedProvider`**: one implementation per content source (`TumblrService`, `BlueSkyService`, `RedditService`), bound via tagged bindings in `AppServiceProvider` and resolved as `app(FeedProvider::class . ':' . $provider)`.
- **`App\Contracts\WebhookProvider`**: one implementation per outbound consumer (currently `DiscordWebhookProvider`), same tagged-binding pattern, resolved by a `{provider}` route parameter. `WebhookController::handle()` is fully generic. It has zero platform-specific knowledge baked in (no hardcoded field names, no hardcoded response shapes). Every platform-specific detail lives inside that platform's own provider class.

Everything else (`SyncFeedSource`, the job that actually fetches and persists posts; `FeedSelector`, topic-aware random post selection; the admin controllers) is provider-agnostic and shouldn't need to change when a new provider or consumer is added.

## Testing

Tests are split by what they actually exercise, not by history:

- `tests/Unit/`: pure logic, no DB or HTTP (parsers, signature verification, the text filter, etc.)
- `tests/Feature/`: HTTP request/response cycles and DB-touching logic
- `tests/Browser/`: kept as a folder name for historical and tooling reasons, but most of what's in here today is really Feature-shaped

The pre-commit hook and CI run `vendor/bin/pest tests/Browser tests/Feature tests/Unit` (via `composer test:browser`, where the name is legacy and now covers all three folders), so everything gets caught regardless of which folder it lives in.

Static analysis runs with `composer analyse` (Larastan at level 5). It runs in the pre-commit hook and in CI, ahead of the slower steps, so type errors fail fast. Level 6 is a planned next step. Most of its findings are missing descriptions of what's inside arrays and Collections.

**Gotcha to watch for:** Pest only discovers files matching `*Test.php` exactly. A lowercase `t` (`...providertest.php` instead of `...ProviderTest.php`) means the file is silently excluded from every run, with no error and no warning. This happened for real in this project, so double-check filenames if a test you know you wrote isn't showing up in the run output at all.

`pest-plugin-browser` is currently back on the main package (no longer pinned to a fork). One browser test involving image upload is currently **commented out** pending a fix, because it broke against the main package's current behavior. Worth restoring once that's sorted out, and not letting it sit commented out indefinitely.

## Deployment

Not currently deployed. See [`DEPLOYMENT.md`](DEPLOYMENT.md) for the full plan: a small DigitalOcean droplet running MySQL, Nginx, Redis, and a supervised queue worker, with real current pricing and the reasoning behind each choice.

## Known limitations

- **Reddit is parked.** `RedditService` is kept in the codebase (and its tests kept in `tests/Unit`) in case Reddit's API access policy changes, but it's not currently active, because Reddit now requires login for the endpoints this project used.
- **Pinterest is parked.** `PinterestService` exists but isn't registered, because no usable feed has been found yet.
- **Tumblr coverage is bounded.** The service walks back up to five pages (100 posts) per tag per sync. A tag that posts faster than that between syncs can still lose posts. The page count is adjustable, see [`docs/tumblr-pagination.md`](docs/tumblr-pagination.md).
- **Redis must use the `predis` client**, not `phpredis`. This is a deliberate choice, so don't switch it.

The webhook endpoint (`/api/webhook/{provider}`) is rate limited to 30 requests per minute per IP.

## Project history

This started as a Laravel tutorial project (a basic Ideas CRUD app) and grew from there into the aggregator described above.


