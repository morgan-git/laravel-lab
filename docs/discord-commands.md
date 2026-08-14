# Discord commands

The Discord bot ("Labbotameme") receives slash commands via **HTTP interactions**, not a persistent Gateway connection — there's no always-on bot process. Discord POSTs directly to your app whenever a user runs a command, your app responds synchronously, done.

## How a command reaches your app

1. A user runs `/foodporn` (or whichever topic-named command) in a server the app is installed to.
2. Discord POSTs the interaction to your app's registered **Interactions Endpoint URL** — locally, this is an ngrok tunnel pointed at `/api/webhook/discord`; in production, it'll be `https://afterthesyntax.com/api/webhook/discord` or similar.
3. This hits `WebhookController::handle()`, a **generic** endpoint — the route is actually `POST /api/webhook/{provider}`, and Discord just happens to be the `provider` value `discord`. The controller resolves `App\Webhooks\DiscordWebhookProvider` via a tagged binding and delegates every platform-specific decision (signature scheme, ping detection, response shape) to it.
4. `DiscordWebhookProvider::verify()` confirms the request is genuinely from Discord (see below), then either answers a `PING` or looks up a post for the topic and responds with a formatted embed.

The endpoint is rate-limited to **30 requests/minute per IP** (`throttle:30,1` on the route).

## Environment variables

| Variable | What it's for | Where to get it |
|---|---|---|
| `DISCORD_PUBLIC_KEY` | Verifying Ed25519 signatures on every incoming request | Discord Developer Portal → your app → General Information |
| `DISCORD_BOT_TOKEN` | Authenticating the one-off REST calls used to register/update slash commands | Discord Developer Portal → your app → Bot tab |

`DISCORD_BOT_TOKEN` is **not** used at runtime by the webhook flow itself — only `DISCORD_PUBLIC_KEY` is. The bot token is purely a tool for the command-registration step below. Regenerating it doesn't break anything currently running.

## Signature verification

`DiscordWebhookProvider::verify()` checks the `X-Signature-Ed25519` and `X-Signature-Timestamp` headers against `DISCORD_PUBLIC_KEY`, using PHP's built-in `sodium` extension (no external package needed — confirm the extension is present on whatever server you deploy to, via `php -m | grep sodium`).

Any request that fails verification gets logged to `webhook_requests` with `status: unauthorized` and a `401` response — it never reaches the topic-lookup logic at all.

## The command-per-topic design (and its tradeoff)

Each topic has its **own registered slash command** — `/foodporn`, `/cooking`, etc. — rather than a single command with a topic option (like `/feed topic:foodporn`). `DiscordWebhookProvider::action()` pulls the topic straight from the command name (`data.name`).

This is nicer to type as a user, but it comes with a real cost: **adding a new topic requires a Discord-side command registration**, not just a database row. The topic system itself (the `Topic` model, `feed_sources.topic_id`) was built specifically so that adding a source didn't require touching Discord at all — this design re-introduces that coupling for the Discord surface specifically. Worth keeping in mind if topics start changing often; a single parameterized command would remove this step entirely at the cost of a slightly clunkier command to type.

## Registering a new topic's command

One-time REST call per new topic, using the bot token:

```bash
curl -X POST https://discord.com/api/v10/applications/{application_id}/commands \
  -H "Authorization: Bot ${DISCORD_BOT_TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
        "name": "cooking",
        "description": "Get a random post from the cooking topic",
        "type": 1
      }'
```

`name` must exactly match the `Topic`'s `name` in the database — the controller (via `DiscordWebhookProvider::action()`) does a direct string match, no slugification currently happens on either side.

## Local development loop

1. Start your local server (`composer dev` / `process-up.sh`).
2. Start an ngrok tunnel to it: `ngrok http https://laracastslessons.test --host-header=rewrite` (or whatever your Herd domain is).
3. **Free ngrok URLs are per-tunnel-restart unless you've claimed a static dev domain** (`your-name.ngrok-free.dev`) — grab the current URL before the next step, every time you restart the tunnel.
4. In the Discord Developer Portal → General Information, set **Interactions Endpoint URL** to `https://<your-ngrok-url>/api/webhook/discord` and save.
   - The moment you save, Discord fires a real `PING` at your endpoint — this is the first genuine test of `verify()` against Discord's real keys, not test-mocked ones.
   - If Discord's dashboard rejects the URL with a generic "Not a well formed URL" error even though the URL is valid and reachable, that's a known dashboard-side validation quirk — set it directly via the API instead:
     ```bash
     curl -X PATCH https://discord.com/api/v10/applications/{application_id} \
       -H "Authorization: Bot ${DISCORD_BOT_TOKEN}" \
       -H "Content-Type: application/json" \
       -d '{"interactions_endpoint_url": "https://<your-ngrok-url>/api/webhook/discord"}'
     ```
5. Register any commands you need (see above).
6. Invite the app to a personal test server via OAuth2 → URL Generator, scope `applications.commands` only (no `bot` scope, no gateway permissions needed — interactions don't require a persistent connection).
7. Run the command for real in that server.

## Response formatting

`DiscordWebhookProvider::formatPayload()` builds the actual Discord-shaped response:

- **Post found:** a rich embed — `title` (truncated to 256 characters with a trailing `...` if longer, since Discord rejects longer embed titles outright), `url` (only included if the post has one), `color` (a fixed value), and `image` (only included if the post has an image).
- **No post found for the topic:** a plain `content` message: `No posts found for "{topic}".` — this covers both "topic exists but has zero posts" and "no such topic at all" (a stale or typo'd command), which look identical from the controller's point of view.

`DiscordWebhookProvider::pingResponse()` is kept deliberately separate from this — Discord's ping ack is a bare `{"type": 1}`, not wrapped in the `{"type": 4, "data": ...}` shape a normal command response uses.

## Logging

Every inbound request — verified or not, even for a provider name that isn't registered at all — gets a row in `webhook_requests`:

| `status` | Meaning |
|---|---|
| `pending` | Row created, before verification runs |
| `unknown_provider` | The `{provider}` route segment doesn't match any registered `WebhookProvider` binding (e.g. a typo'd URL, or a probe hitting the endpoint) |
| `unauthorized` | Signature verification failed |
| `ping` | Discord's `PING` handshake, answered with `{"type": 1}` |
| `success` | A post was found and returned |
| `failed` | No post found for the topic (or the topic doesn't exist) |

`payload_in` and `payload_out` are stored as JSON for full request/response visibility when debugging.

## Testing

Two files together cover this end to end:

- `tests/Unit/DiscordWebhookProviderTest.php` — every method on `DiscordWebhookProvider` in isolation: signature verification, ping detection, requester/action extraction, payload formatting.
- `tests/Feature/WebhookControllerTest.php` — the full real HTTP request cycle (real Ed25519 signing, real routing through `{provider}`, real DB assertions on `webhook_requests`), proving the generic controller and the Discord-specific provider actually work together, not just in isolation.

Copy both files' structure (particularly the bound-closure request-building/signing helpers on `$this`, not global functions — see the comments at the top of each file for why) when adding coverage for a new consumer.
