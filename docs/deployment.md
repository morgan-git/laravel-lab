# Deployment

This doc exists to make deployment decisions **before** the droplet exists, not while SSH'd into it at 2am. A few of these are genuine open decisions, not settled facts — flagged explicitly below rather than silently picked.

## Cost reality check

The $200 DigitalOcean new-account credit is no longer available. Actual current pricing (per-second billing, capped at a monthly max):

| Plan | Specs | Cost |
|---|---|---|
| Basic (smallest) | 1 vCPU, 512MB RAM, 10GB disk | $4/mo |
| Basic | 1 vCPU, 1GB RAM, 25GB disk | $6/mo |

**Recommendation: the $6/mo, 1GB plan, not the $4/mo one.** 512MB is tight for PHP-FPM + Nginx + Redis + a queue worker process all running simultaneously — you'd likely need a swap file just to avoid OOM kills under any real load, and debugging OOM issues on a $4 box isn't worth the $2/mo saved. This is a guess based on the stack, not a measurement — if budget is genuinely the deciding factor, start at $4/mo with swap configured and upgrade if things get killed.

**Backups add 20-30% on top** (weekly vs daily) — worth having, but that's an extra ~$1.20-1.80/mo on the $6 plan. Decide whether to enable this before or after the first real deploy; not a blocker either way.

## Open decision: database

Local dev uses SQLite. Production **could** keep using SQLite too — it's simpler, zero extra cost, and this is a low-traffic portfolio project — but worth naming the real tradeoff before defaulting into it:

- We already hit a real `SQLITE_BUSY` / "database is locked" error locally, from a migration running concurrently with something else touching the DB. In production, the queue worker and web requests will be hitting the same SQLite file concurrently, continuously — that failure mode becomes a live possibility, not a one-off.
- A managed Postgres/MySQL database on DigitalOcean starts at a real additional monthly cost (not free), which cuts against the tight-budget constraint this whole plan is built around.

**This needs an explicit decision, not a default.** If staying on SQLite: enable WAL mode (`PRAGMA journal_mode=WAL;`) at minimum, which meaningfully reduces (doesn't eliminate) lock contention between concurrent readers/writers. If moving to a managed DB: budget for it before committing to the droplet size above, since it's a separate line item.

## Server requirements

- PHP 8.4 with the extensions this app actually uses: `sodium` (webhook signature verification — **confirm this before deploying**, don't assume it's there), `pdo_sqlite` (or the appropriate PDO driver per the database decision above), `redis` or rely on `predis` (pure-PHP, no extension needed — this project already deliberately uses `predis` over `phpredis`, so no native Redis extension is required here)
- Nginx (or your preferred web server) + PHP-FPM
- Redis server itself (`apt install redis-server`, not Homebrew — that was Herd's setup)
- Composer
- Node.js + npm, **only if building assets on the server** — building locally and deploying the built `public/build` directory avoids needing Node on the droplet at all, which is simpler for a $6/mo box
- Certbot, for Let's Encrypt

## Initial server setup

1. Create the droplet (Ubuntu LTS is the safe default), add your SSH key at creation time rather than password auth.
2. Create a non-root deploy user; don't run the app as root.
3. Set up `ufw` (or DigitalOcean's Cloud Firewall) to allow only SSH, HTTP, HTTPS.
4. `apt update && apt upgrade` before installing anything else.

## Deploying the app

1. Clone the repo onto the droplet (or push via CI later — manual clone is fine for a first deploy).
2. `composer install --no-dev --optimize-autoloader`
3. Copy `.env.example` → `.env`, fill in **production** values — this is a different file from your local `.env`, don't copy your dev one over:
   - `APP_ENV=production`, `APP_DEBUG=false`
   - `DISCORD_PUBLIC_KEY` (same value as local — it's tied to the Discord app, not the environment)
   - Database config per the decision above
   - `QUEUE_CONNECTION=database` (matches local)
4. `php artisan key:generate`
5. `php artisan migrate --force`
6. Either build assets locally and `rsync`/`scp` the `public/build` directory up, or run `npm install && npm run build` on the droplet if Node's installed there.

## Queue worker and scheduler — do NOT reuse `process-up.sh`

`scripts/process-up.sh` is a local-dev convenience script — backgrounded processes with `&` and `disown`, no supervision, no auto-restart on crash or reboot. **This is exactly the category of problem that caused the zombie `queue:work` process bug already hit once in local dev** — a background process silently outliving the intent behind it, invisible to any restart tooling that doesn't know its exact process name. Don't ship that same failure mode to production, where nobody's watching a terminal to notice.

Use `systemd` (or Supervisor) instead, so crashed/stale processes actually get detected and restarted rather than quietly rotting:

- A `laravel-queue.service` unit running `php artisan queue:work` (not `queue:listen` — for production, `queue:work` with a process supervisor handling restarts on code deploys is the standard pattern; `queue:listen`'s per-job reboot is more of a local-dev convenience for picking up code changes without a manual restart)
- A `laravel-schedule.service` (or a cron entry calling `schedule:run` every minute) for the scheduled Tumblr/Bluesky syncs

**Every deploy that changes queue-job code needs a `systemctl restart laravel-queue`** (or `php artisan queue:restart`, which signals workers to exit after their current job — only useful if something is actually set up to restart them afterward). This is the exact bug that cost a full day locally — worth being deliberate about it here since production has no one manually noticing "huh, that seems stale."

## Web server + SSL

1. Nginx vhost pointing at `public/`, PHP-FPM socket configured.
2. `certbot --nginx -d afterthesyntax.com` for Let's Encrypt — free, auto-renewing.
3. Confirm the cert covers whatever subdomain the Discord Interactions Endpoint URL will actually use.

## DNS

Point `afterthesyntax.com`'s A record at the droplet's IP. Propagation can take a few minutes to a few hours — don't register the Discord Interactions Endpoint URL against the production domain until DNS has actually propagated and resolves.

## UptimeRobot — what it's actually for here

The original plan described UptimeRobot as pinging every 5 minutes "to keep it alive." **That reasoning doesn't apply to a DigitalOcean droplet** — a paid always-on VPS doesn't sleep the way a free-tier PaaS dyno (Heroku's old free tier, for example) does. There's nothing to "keep alive."

What UptimeRobot is still genuinely useful for: **uptime monitoring and alerting** — get notified if the droplet or app goes down for any reason (crash, out-of-memory kill, Nginx misconfig). Set it up for that reason, not the original one.

## Post-deploy smoke test

Before registering the production Discord endpoint or telling anyone the site is live:

1. Hit `/up` (Laravel's built-in health check route) — confirms the app boots at all.
2. `systemctl status laravel-queue` — confirms the queue worker is actually running, not just that the deploy script didn't error.
3. Manually dispatch a sync from the admin dashboard, check `webhook_requests`/`feed_posts` for real activity — confirms DB writes work end to end in the new environment.
4. **Only after 1-3 pass:** update the Discord Interactions Endpoint URL to the production domain, and confirm the real `PING` handshake succeeds against production `DISCORD_PUBLIC_KEY` verification — same as the original ngrok test, just against the real server this time.
5. Re-register slash commands if the application ID differs between a dev/test Discord app and the production one (confirm whether you've been testing against the same Discord application this whole time, or a separate dev app — if separate, commands need registering fresh against production).

## Rollback

Not yet defined. At minimum: know how to `git checkout` a previous commit and re-run `composer install`/`migrate` before the first real deploy, even if that's the whole rollback plan for now. Worth a proper strategy (tagged releases, atomic symlink swaps, etc.) once this is live and stable rather than blocking the first deploy on it.
