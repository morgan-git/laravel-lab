# Tumblr Pagination

`TumblrService::fetch()` walks back through several pages of a tag instead of
only reading the newest 20 posts. This exists so a busy tag can't scroll posts
out of the fetch window between one sync and the next.

## Signature

```php
public function fetch(string $tag, int $maxPages = 5): Collection
```

Existing callers that only pass `$tag` keep working. They just get deeper
coverage by default: up to 5 pages of 20, so up to 100 raw posts per tag per
sync.

## How the walk works

1. Request the newest page from Tumblr's `/v2/tagged` endpoint (`limit=20`).
2. Take the oldest `timestamp` on that page and send it as the `before`
   parameter on the next request, which returns the next-older page.
3. Repeat until a stop condition is hit.
4. Run every collected raw post through `parseResponse()` once, at the end.

## Stop conditions

The loop stops at whichever comes first:

- **Empty page.** Nothing older came back, or the request failed.
- **Short page.** Fewer than 20 raw posts means Tumblr has nothing older for
  this tag right now, so there is no point asking again. This count is taken
  before any filtering.
- **Page cap.** `$maxPages` requests have been made (default 5).
- **No usable timestamp.** A page whose posts have no `timestamp` cannot
  produce a `before` value, so the walk ends there.

## Failure behavior

Each page is fetched by `fetchPage()`, which returns an empty collection on a
non-200 response, malformed JSON, or any thrown error. A failure partway
through does not throw and does not discard earlier pages. Pagination stops
and the posts already collected are returned.

## Filtering and dedupe

Filtering happens once, after all pages are collected, not per page:

- Blocked blog prefixes are dropped.
- Posts with no image are dropped.
- Off-topic and non-English content is skipped.
- Known content-farm title prefixes are stripped from titles.
- Posts are deduplicated by `dedupe_key` across the whole set, so the same
  post appearing on two pages, or a content-farm repost, only survives once.

Downstream, `SyncFeedSource` is idempotent through `updateOrCreate` and
`dedupe_key`, so fetching a wider window than before does not create duplicate
rows on repeat syncs.

## Tuning `$maxPages`

The default of 5 is a starting guess, not a measured value. To change it for
one caller, pass it explicitly:

```php
$posts = $tumblr->fetch('foodporn', maxPages: 10);
```

Raise it if a busy tag still loses posts between syncs. Lower it if 100 posts
per tag is more than you need. Each extra page is one more HTTP request per
tag per sync.

## Known edge case

`before` is a plain "older than this timestamp" cursor. If two posts share the
exact same second at a page boundary, one could in theory be skipped. In
practice Tumblr timestamps are fine-grained enough that this rarely matters,
and cross-page dedupe covers the duplicate direction. If a gap ever shows up
in real data, the page boundary is the first place to look.

## Tests

`tests/Unit/TumblrServiceTest.php` (adjust to your actual path) covers:

- a second page is requested with the correct `before` value after a full page
- the walk stops after `$maxPages` even when every page is full
- the walk stops when an empty page comes back
- non-200 and malformed JSON responses return an empty collection
