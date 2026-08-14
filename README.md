# afterthesyntax

A multi-provider content aggregator that pulls posts from Tumblr and Bluesky (Reddit is currently parked — see [Known Limitations](#known-limitations)), organizes them by topic, and serves that content two ways: a public web feed, and a Discord bot that answers slash commands with a random post from a topic.

Originally started as a Laravel tutorial project (a basic Ideas CRUD app) and evolved from there — this repo is a solo portfolio piece, not open for outside contributions.

## What it does

- **Ingests** content from multiple providers on a schedule, normalized into a common shape regardless of source.
- **Organizes by topic** rather than by raw provider/handle — a `Topic` like "foodporn" can be backed by any number of Tumblr tags, Bluesky searches, etc., and the web feed / Discord bot never need to know which.
- **Serves via a public web feed** at `/feed`, browsable by topic.
- **Serves via Discord slash commands** — `/foodporn`, `/cooking`, etc. — each registered per-topic, returning a random post as a rich embed.
- **Admin dashboard** for managing feed sources (add/edit/pause/toggle visibility/manually trigger a sync), users (admin access toggle), and the job queue (view pending/failed jobs, retry or cancel).

## Stack

- Laravel 13, PHP 8.4
- Pest (with `pest-plugin-browser` — see [Testing](#testing) for a currently-known gap)
- Redis (via `predis`, **not** `phpredis` — see [Known Limitations](#known-limitations)) for caching
- SQLite for local development
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

(pass `-r` / `--restart` to force-restart anything already running — **note:** this script currently only watches for `queue:listen`, not `queue:work`; if you ever manually start a worker with `queue:work` instead, this script won't see it. Kill it by hand before switching back — this has genuinely caused a full day of "why is nothing updating" debugging before, worth not repeating.)

You'll also need:

- **Redis** running locally via Homebrew (`brew services start redis`)
- A `.git/hooks/pre-commit` copied from `scripts/pre-commit` (not installed automatically on clone)

### Environment variables

Beyond the standard Laravel `.env` values, this project needs:

```
DISCORD_PUBLIC_KEY=   # from the Discord Developer Portal — see docs/discord-commands.md
DISCORD_BOT_TOKEN=    # only needed for one-off slash command registration, not runtime
```

### Seeding feed sources

Feed sources (which provider/handle backs which topic) are managed through the admin dashboard at `/admin/feed-sources` — no need to seed via `tinker`.

## Architecture

Two provider-style contracts drive most of the extensibility here:

- **`App\Contracts\FeedProvider`** — one implementation per content source (`TumblrService`, `BlueSkyService`, `RedditService`), bound via tagged bindings in `AppServiceProvider` and resolved as `app(FeedProvider::class . ':' . $provider)`. See [`docs/adding-a-provider.md`](docs/adding-a-provider.md) for how to add a new one.
- **`App\Contracts\WebhookProvider`** — one implementation per outbound consumer (currently `DiscordWebhookProvider`), same tagged-binding pattern, resolved by a `{provider}` route parameter. `WebhookController::handle()` is fully generic — it has zero platform-specific knowledge baked in (no hardcoded field names, no hardcoded response shapes); every platform-specific detail lives inside that platform's own provider class. See [`docs/adding-a-consumer.md`](docs/adding-a-consumer.md) for how to add another (Slack, etc.).

Everything else — `SyncFeedSource` (the job that actually fetches and persists posts), `FeedSelector` (topic-aware random post selection), the admin controllers — is provider-agnostic and shouldn't need to change when a new provider or consumer is added.

## Testing

Tests are split by what they actually exercise, not by history:

- `tests/Unit/` — pure logic, no DB/HTTP (parsers, signature verification, etc.)
- `tests/Feature/` — HTTP request/response cycles and DB-touching logic
- `tests/Browser/` — kept as a folder name for historical/tooling reasons, but most of what's in here today is really Feature-shaped

The pre-commit hook and CI run `vendor/bin/pest tests/Browser tests/Feature tests/Unit` (via `composer test:browser` — the name is legacy, it now covers all three folders), so everything gets caught regardless of which folder it lives in.

**Gotcha to watch for:** Pest only discovers files matching `*Test.php` exactly — a lowercase `t` (`...providertest.php` instead of `...ProviderTest.php`) means the file is silently excluded from every run, no error, no warning, it just never executes. This happened for real in this project — double-check filenames if a test you know you wrote isn't showing up in the run output at all.

`pest-plugin-browser` is currently back on the main package (no longer pinned to a fork). One browser test involving image upload is currently **commented out** pending a fix — it broke against the main package's current behavior. Worth restoring once that's sorted out; don't let it sit commented-out indefinitely.

## Deployment

Not yet live. See [`DEPLOYMENT.md`](DEPLOYMENT.md) for the full pre-deploy plan, including real current DigitalOcean pricing (the $200 new-account credit no longer exists) and a couple of open decisions (database choice, droplet sizing) that are worth making deliberately rather than defaulting into during the actual deploy.

## Known limitations

- **Reddit is parked.** `RedditService` is kept in the codebase (and its tests kept in `tests/Unit`) in case Reddit's API access policy changes, but it's not currently active — Reddit now requires login for the endpoints this project used.
- **Tumblr's tagged endpoint caps at 20 posts per request.** A post can scroll out of that window before a sync ever catches it, on an active tag. Pagination via Tumblr's `before` parameter would fix this but isn't implemented yet.
- **No "has seen" dedup/expiry yet.** `webhook_sent_posts` exists as a table but the logic to actually prevent Discord from serving the same post twice in a row isn't built.
- **Redis must use the `predis` client**, not `phpredis` — a deliberate choice, don't switch it.

The webhook endpoint (`/api/webhook/{provider}`) is rate-limited to 30 requests/minute per IP.

## Contributing

This is a solo portfolio project and isn't set up to take outside contributions.
