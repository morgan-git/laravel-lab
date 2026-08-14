# Adding a new consumer

A "consumer" here means an outbound integration that receives a formatted post — Discord today, potentially Slack, a webhook for some other chat platform, etc. tomorrow. This mirrors the pattern used for `FeedProvider` (see `docs/adding-a-provider.md`), just on the output side instead of the input side.

`WebhookController::handle()` is fully generic — it resolves which provider to use from a `{provider}` route parameter and has no platform-specific knowledge of its own. Adding a consumer genuinely is "one new class + one new binding," the same as adding a `FeedProvider`.

## The contract

```php
interface WebhookProvider
{
    public function verify(Request $request): bool;
    public function isPing(Request $request): bool;
    public function pingResponse(): JsonResponse;
    public function requesterId(Request $request): string;
    public function requesterType(Request $request): string;
    public function action(Request $request): string;
    public function formatPayload(?FeedPost $post, string $topic): array;
    public function respond(array $payload): JsonResponse;
}
```

What each method is responsible for:

- **`verify()`** — confirm the incoming request is genuinely from the platform it claims to be from. Entirely platform-specific: Discord uses Ed25519 signatures via `sodium`; Slack, for comparison, uses HMAC-SHA256 against a signing secret with a different header scheme entirely. There's no shared crypto logic to reuse — each implementation is standalone.
- **`isPing()`** — whether this request is the platform's own handshake/health-check, distinct from a real command. If your platform doesn't have this concept, just always return `false`.
- **`pingResponse()`** — the response to a ping specifically. Kept separate from `respond()` because a ping's expected shape is often *not* the same shape a normal response uses — Discord's ping ack is a bare `{"type": 1}`, while a real command response is wrapped in `{"type": 4, "data": ...}`.
- **`requesterId()` / `requesterType()`** — a stable identifier (and a short label for what kind of identifier it is) for whoever sent the request, purely for the `webhook_requests` log. Discord's is `guild_id` / `"guild"`; another platform's might be a team ID, channel ID, or something else.
- **`action()`** — the topic being requested, extracted from wherever this platform puts it. Discord's is the slash command name (`data.name`); a different platform might use a query param, a URL path segment, or an option value instead.
- **`formatPayload()`** — build the platform-specific representation of either a found post or a "nothing found" message.
- **`respond()`** — wrap a payload in whatever acknowledgment shape the platform expects for a normal (non-ping) response.

## Steps to add one

1. Create `App\Webhooks\{Platform}WebhookProvider implements WebhookProvider`.
2. Implement `verify()` against that platform's actual signature/auth scheme — go read that platform's docs for how they expect you to validate requests, don't reuse Discord's `sodium`-based logic.
3. Implement `isPing()` / `pingResponse()` if the platform has a handshake concept; otherwise `isPing()` can just `return false;`.
4. Implement `requesterId()` / `requesterType()` / `action()` by pulling the right fields out of that platform's request shape.
5. Implement `formatPayload()` to build whatever shape that platform's API expects (Slack's Block Kit, for example, looks nothing like a Discord embed).
6. Implement `respond()` to match the platform's expected acknowledgment shape.
7. Register a tagged binding in `AppServiceProvider`, following the same pattern as Discord:
   ```php
   $this->app->bind(WebhookProvider::class . ':slack', SlackWebhookProvider::class);
   ```
8. That's it for routing — `POST /api/webhook/slack` will now resolve to your new provider automatically, no controller changes needed. It also automatically inherits the existing rate limit (`throttle:30,1`) and the `webhook_requests` logging (including the `unknown_provider` status if the binding is ever missing/misspelled) for free.
9. Write a feature test modeled on `tests/Feature/WebhookControllerTest.php`, and a unit test modeled on `tests/Unit/DiscordWebhookProviderTest.php` — real signed requests, real HTTP calls, real DB assertions, not just isolated unit tests of the provider class.

## Coupled by design: `FeedPost` in the contract signature

`formatPayload(?FeedPost $post, string $topic)` means `WebhookProvider` isn't a fully platform-agnostic contract — it's specifically "format a `FeedPost` for platform X." That's a deliberate, reasonable choice for this project (every consumer here exists to relay feed content, there's no other kind of payload in play), not an oversight. Just don't mistake this contract for something more generic than it is if reused elsewhere.
