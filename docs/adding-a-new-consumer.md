# Adding a new consumer

A "consumer" here means an outbound integration that receives a formatted post — Discord today, potentially Slack, a webhook for some other chat platform, etc. tomorrow. This mirrors the pattern used for `FeedProvider` (see `docs/adding-a-provider.md`), just on the output side instead of the input side.

## The contract

```php
interface WebhookProvider
{
    public function verify(Request $request): bool;
    public function respond(array $payload): JsonResponse;
    public function formatPayload(?FeedPost $post, string $topic): array;
}
```

- **`verify()`** — confirm the incoming request is genuinely from the platform it claims to be from. This is entirely platform-specific: Discord uses Ed25519 signatures via `sodium`; Slack, for comparison, uses HMAC-SHA256 against a signing secret with a different header scheme entirely. There's no shared crypto logic to reuse here — each implementation is standalone.
- **`respond()`** — wrap a payload in whatever acknowledgment shape the platform expects. For Discord this is `{"type": 4, "data": {...}}`. A different platform likely expects something else entirely.
- **`formatPayload(?FeedPost $post, string $topic)`** — build the platform-specific representation of either a found post or a "nothing found" message. This is intentionally coupled to `FeedPost` directly rather than being fully generic — see the caveat below.

## Steps to add one

1. Create `App\Webhooks\{Platform}WebhookProvider implements WebhookProvider`.
2. Implement `verify()` against that platform's actual signature/auth scheme. Don't reuse Discord's `sodium`-based logic — go read that platform's docs for how they expect you to validate requests.
3. Implement `formatPayload()` to build whatever shape that platform's API expects (Slack's Block Kit, for example, looks nothing like a Discord embed).
4. Implement `respond()` to match the platform's expected acknowledgment shape.
5. Register a tagged binding in `AppServiceProvider`, following the same pattern as Discord:
   ```php
   $this->app->bind(WebhookProvider::class . ':slack', SlackWebhookProvider::class);
   ```
6. Add routing and a controller entry point (see the honest caveat below — this is the one part that isn't drop-in yet).
7. Write a feature test modeled on `tests/Feature/WebhookControllerTest.php` — real signed requests, real HTTP calls, real DB assertions on the `webhook_requests` log, not just unit tests of the provider class in isolation.

## Caveat: the controller isn't fully generic yet

Unlike `FeedProvider`, where adding a new source is genuinely just "one new class + one new binding," `WebhookProvider` currently has one piece of friction: `WebhookController` has a Discord-specific method, `discord()`, not a generic `handle(string $provider)` dispatch. Adding Slack today means either:

- adding a parallel `slack()` method to the same controller (quick, but duplicates the verify → formatPayload → respond → log flow for every consumer), or
- refactoring `WebhookController` to a single generic method that resolves the right `WebhookProvider` binding from a route parameter (cleaner, matches the `FeedProvider::class . ':' . $provider` resolution pattern already used elsewhere, but is a real refactor, not just an addition)

Worth doing the refactor before a second consumer actually gets added, rather than after — the second copy-pasted `{platform}()` method is exactly the kind of thing that's easy to add "just this once" and annoying to unwind later.

## Also coupled: `FeedPost` in the contract signature

`formatPayload(?FeedPost $post, string $topic)` means `WebhookProvider` isn't a fully platform-agnostic contract — it's specifically "format a `FeedPost` for platform X." That's a deliberate, reasonable choice for this project (every consumer here exists to relay feed content, there's no other kind of payload in play), not an oversight. Just don't mistake this contract for something more generic than it is if reused elsewhere.
