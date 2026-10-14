# Deployment

This doc exists to make deployment decisions **before** the droplet exists, not while SSH'd into it at 2am. The database decision is settled (MySQL on the droplet). The items still open are flagged explicitly below rather than silently picked.

## Cost reality check

The $200 DigitalOcean new-account credit is no longer available. Actual current pricing (per-second billing, capped at a monthly max):

| Plan | Specs | Cost |
|---|---|---|
| Basic (smallest) | 1 vCPU, 512MB RAM, 10GB disk | $4/mo |
| Basic | 1 vCPU, 1GB RAM, 25GB disk | $6/mo |

**Recommendation: the $6/mo, 1GB plan, not the $4/mo one.** 512MB is tight for PHP-FPM + Nginx + Redis + a queue worker process all running simultaneously, and with MySQL on the same box it stops being realistic. You'd likely need a swap file just to avoid OOM kills under any real load, and debugging OOM issues on a $4 box isn't worth the $2/mo saved. This is a guess based on the stack, not a measurement. If budget is genuinely the deciding factor, start at $4/mo with swap configured and the lean MySQL settings below, and upgrade if things get killed.

**Backups add 20-30% on top** (weekly vs daily). Worth having, but that's an extra ~$1.20-1.80/mo on the $6 plan. Decide whether to enable this before or after the first real deploy; not a blocker either way. They do not replace the database dumps described below.

## Database: MySQL on the droplet (decided)

Local dev keeps using SQLite. Production runs MySQL installed on the same droplet, which is the cheapest and simplest MySQL option and adds no extra monthly line item.

**Why not SQLite in production:** the queue worker and web requests write to the database at the same time, continuously. We already hit a real "database is locked" error locally, from a migration running concurrently with something else touching the DB. MySQL handles concurrent writers without that failure mode.

**Why not managed MySQL from day one:** it's a separate monthly cost on a small personal project, and this plan is built around a tight budget. It stays available as an upgrade path (see "When to revisit" below).

**It shares 1GB with everything else.** MySQL's default settings assume a dedicated server. Starting points for a small box (not measured, adjust if you see memory pressure), in `/etc/mysql/mysql.conf.d/lowmem.cnf`:

```
[mysqld]
performance_schema = OFF
innodb_buffer_pool_size = 128M
max_connections = 40
```

Also add a swap file as a safety net. MariaDB is a lighter drop-in replacement if MySQL still turns out to be too heavy (Laravel has a `mariadb` connection driver for it).

**Keep it private.** MySQL on Ubuntu listens on localhost only by default. Leave it that way and never open port 3306 in the firewall. The app connects with its own limited user, not root.

**Dev/prod parity.** Local dev and the test suite run on SQLite, so MySQL's behavior hasn't been exercised yet. SQLite doesn't enforce string lengths the way MySQL does, and MySQL's default collation treats `Foo` and `foo` as the same value in comparisons and unique indexes. Before the first real deploy, run `migrate` and the test suite against a throwaway MySQL database at least once (on the droplet itself works) and fix whatever turns up.

**Backups.** DigitalOcean droplet backups copy the whole disk, which for a running database is a point-in-time copy, not a clean export. Add a nightly dump as well, via cron:

```
0 3 * * * mysqldump --single-transaction --no-tablespaces afterthesyntax | gzip > /home/deploy/backups/afterthesyntax-$(date +\%F).sql.gz
```

Put the app user's credentials in `~/.my.cnf` (mode 600) under `[client]` so the password isn't on the command line, and prune old dumps now and then. A dump sitting on the same disk doesn't survive losing the droplet, so copy one off the box occasionally (`scp` to your laptop is enough to start).

**When to revisit managed MySQL:** if MySQL is the thing getting OOM-killed, if you want automated or point-in-time backups, or if the data becomes something you can't afford to lose. Moving is a dump, an import, and changing the `DB_*` values in `.env`. Check DigitalOcean's current managed database pricing before deciding, since it's a separate line item.

## Server requirements

- PHP 8.4 with the extensions this app actually uses: `sodium` (webhook signature verification: **confirm this before deploying**, don't assume it's there), `pdo_mysql` (the PHP MySQL driver), and `redis` or rely on `predis` (pure PHP, no extension needed. This project already deliberately uses `predis` over `phpredis`, so no native Redis extension is required here)
- MySQL server (`apt install mysql-server`)
- Nginx (or your preferred web server) + PHP-FPM
- Redis server itself (`apt install redis-server`, not Homebrew, that was Herd's setup)
- Composer
- Node.js + npm, **only if building assets on the server**. Building locally and deploying the built `public/build` directory avoids needing Node on the droplet at all, which is simpler for a $6/mo box
- Certbot, for Let's Encrypt

## Initial server setup

1. Create the droplet (Ubuntu LTS is the safe default), add your SSH key at creation time rather than password auth.
2. Create a non-root deploy user; don't run the app as root.
3. Set up `ufw` (or DigitalOcean's Cloud Firewall) to allow only SSH, HTTP, HTTPS. Never 3306.
4. `apt update && apt upgrade` before installing anything else.
5. Install MySQL and create the app's database and user:

```
sudo apt install mysql-server
sudo mysql
```

```sql
CREATE DATABASE afterthesyntax CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'afterthesyntax'@'localhost' IDENTIFIED BY 'a-long-random-password';
GRANT ALL PRIVILEGES ON afterthesyntax.* TO 'afterthesyntax'@'localhost';
```

Then add the memory settings from the database section above and restart MySQL.

## Deploying the app

1. Clone the repo onto the droplet (or push via CI later; manual clone is fine for a first deploy).
2. `composer install --no-dev --optimize-autoloader`
3. Copy `.env.example` to `.env`, fill in **production** values. This is a different file from your local `.env`, don't copy your dev one over:
   - `APP_ENV=production`, `APP_DEBUG=false`
   - `DISCORD_PUBLIC_KEY` (same value as local, it's tied to the Discord app, not the environment)
   - `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_PORT=3306`, plus `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD` for the database and user created above
   - `QUEUE_CONNECTION=database` (matches local; the jobs table now lives in MySQL)
4. `php artisan key:generate`
5. `php artisan migrate --force` (the first run against MySQL, so watch for errors and see the dev/prod parity note above)
6. Either build assets locally and `rsync`/`scp` the `public/build` directory up, or run `npm install && npm run build` on the droplet if Node's installed there.

## Queue worker and scheduler: do NOT reuse `process-up.sh`

`scripts/process-up.sh` is a local-dev convenience script: backgrounded processes with `&` and `disown`, no supervision, no auto-restart on crash or reboot. **This is exactly the category of problem that caused the zombie `queue:work` process bug already hit once in local dev**, a background process silently outliving the intent behind it, invisible to any restart tooling that doesn't know its exact process name. Don't ship that same failure mode to production, where nobody's watching a terminal to notice.

Use `systemd` (or Supervisor) instead, so crashed/stale processes actually get detected and restarted rather than quietly rotting:

- A `laravel-queue.service` unit running `php artisan queue:work --timeout=300` (not `queue:listen`: for production, `queue:work` with a process supervisor handling restarts on code deploys is the standard pattern; `queue:listen`'s per-job reboot is more of a local-dev convenience for picking up code changes without a manual restart)
- A `laravel-schedule.service` (or a cron entry calling `schedule:run` every minute) for the scheduled Tumblr/Bluesky syncs
- Match the timeouts: `SyncFeedSource` sets `$timeout = 300`, so make sure `retry_after` for the database connection in `config/queue.php` is comfortably higher than 300 (the default is 90). Otherwise a long sync can be picked up again while it's still running.

**Every deploy that changes queue-job code needs a `systemctl restart laravel-queue`** (or `php artisan queue:restart`, which signals workers to exit after their current job, only useful if something is actually set up to restart them afterward). This is the exact bug that cost a full day locally, so be deliberate about it here since production has no one manually noticing "huh, that seems stale."

## Web server + SSL

1. Nginx vhost pointing at `public/`, PHP-FPM socket configured.
2. `certbot --nginx -d afterthesyntax.com` for Let's Encrypt: free, auto-renewing.
3. Confirm the cert covers whatever subdomain the Discord Interactions Endpoint URL will actually use.

## DNS

Point `afterthesyntax.com`'s A record at the droplet's IP. Propagation can take a few minutes to a few hours, so don't register the Discord Interactions Endpoint URL against the production domain until DNS has actually propagated and resolves.

## UptimeRobot: what it's actually for here

The original plan described UptimeRobot as pinging every 5 minutes "to keep it alive." **That reasoning doesn't apply to a DigitalOcean droplet.** A paid always-on VPS doesn't sleep the way a free-tier PaaS dyno (Heroku's old free tier, for example) does. There's nothing to "keep alive."

What UptimeRobot is still genuinely useful for: **uptime monitoring and alerting**. Get notified if the droplet or app goes down for any reason (crash, out-of-memory kill, Nginx misconfig). Set it up for that reason, not the original one.

## Post-deploy smoke test

Before registering the production Discord endpoint or telling anyone the site is live:

1. Hit `/up` (Laravel's built-in health check route) to confirm the app boots at all.
2. `systemctl status laravel-queue` to confirm the queue worker is actually running, not just that the deploy script didn't error.
3. Manually dispatch a sync from the admin dashboard, check `webhook_requests`/`feed_posts` for real activity. This confirms DB writes work end to end in the new environment.
4. Run the backup command from the database section once by hand and confirm the dump file isn't empty.
5. **Only after 1-4 pass:** update the Discord Interactions Endpoint URL to the production domain, and confirm the real `PING` handshake succeeds against production `DISCORD_PUBLIC_KEY` verification. Same as the original ngrok test, just against the real server this time.
6. Re-register slash commands if the application ID differs between a dev/test Discord app and the production one (confirm whether you've been testing against the same Discord application this whole time, or a separate dev app. If separate, commands need registering fresh against production).

## Rollback

Not yet defined. At minimum: know how to `git checkout` a previous commit and re-run `composer install`/`migrate` before the first real deploy, even if that's the whole rollback plan for now. Rolling back code does not roll back the database: for a bad migration, use `php artisan migrate:rollback` or restore the last dump. Worth a proper strategy (tagged releases, atomic symlink swaps, etc.) once this is live and stable rather than blocking the first deploy on it.
