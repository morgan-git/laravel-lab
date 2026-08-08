<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\FeedProvider;
use GuzzleHttp\Client;
use Illuminate\Support\Collection;

class TumblrService implements FeedProvider
{
    protected Client $client;

    protected string $apiKey;

    /**
     * Off-topic "thanks for the likes" / follow-spam style posts that
     * sometimes get mistagged into content tags. Not exhaustive — just
     * the common patterns worth auto-filtering.
     */
    private const array IGNORED_CONTENT_PATTERNS = [
        '/thanks? (you )?for (the )?(likes?|follows?|reblogs?)/i',
        '/follow for follow/i',
        '/\bf4f\b/i',
        '/check out my (blog|page)/i',
        '/new followers?/i',
    ];

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?? new Client([
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        // Put your consumer key in your .env file (e.g., TUMBLR_API_KEY=your_key_here)
        $this->apiKey = config('services.tumblr.key');
    }

    /**
     * Fetch public posts matching a tag (e.g., "foodporn" or "cooking")
     */
    public function fetch(string $tag): Collection
    {
        try {
            $response = $this->client->get('https://api.tumblr.com/v2/tagged', [
                'query' => [
                    'tag' => trim($tag, '#/'),
                    'api_key' => $this->apiKey,
                    'limit' => 20, // Tumblr's max per request for tagged items
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                return collect();
            }

            $json = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);

            return $this->parseResponse($json);

        } catch (\Throwable) {
            return collect();
        }
    }

    protected function parseResponse(array $json): Collection
    {
        $posts = data_get($json, 'response', []);

        return collect($posts)
            ->map(function ($post): ?array {
                $imageUrl = $this->extractImage($post);

                if (! $imageUrl) {
                    return null;
                }

                $text = $this->extractText($post);

                if ($this->shouldSkip($text)) {
                    return null;
                }

                $title = $text !== '' ? $text : (data_get($post, 'slug') ?: 'Food Post');

                return [
                    'id' => (string) data_get($post, 'id'),
                    'title' => $title,
                    'url' => data_get($post, 'post_url'),
                    'author' => data_get($post, 'blog_name', 'tumblr'),
                    'updated' => date('Y-m-d H:i:s', data_get($post, 'timestamp', time())),
                    'content' => $text,
                    'image' => $imageUrl,
                    // Not persisted — used only to catch content-farm networks
                    // that cross-post the same article across sibling blogs
                    // with different post IDs. Falls back to the image URL for
                    // native photo posts that have no external link.
                    'dedupe_key' => data_get($post, 'link_url') ?: $imageUrl,
                ];
            })
            ->filter()
            ->unique('dedupe_key')
            ->values();
    }

    /**
     * Legacy "photo" type posts have a top-level 'photos' array. Many
     * current posts use Tumblr's newer NPF format instead, where images
     * are embedded as <img> tags inside the 'body' (or 'content') HTML
     * and there's no top-level 'photos' array at all — so we fall back
     * to pulling the first <img src> out of the raw HTML.
     */
    protected function extractImage(array $post): ?string
    {
        $legacyImage = data_get($post, 'photos.0.original_size.url');

        if ($legacyImage) {
            return $legacyImage;
        }

        $html = data_get($post, 'body') ?? data_get($post, 'content') ?? '';

        preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $html, $matches);

        return $matches[1] ?? null;
    }

    /**
     * Tumblr's 'summary' field is truncated server-side with a trailing
     * "...". Prefer the full raw caption/body text instead so titles and
     * descriptions aren't cut off; summary/slug are only a last resort.
     */
    protected function extractText(array $post): string
    {
        $raw = data_get($post, 'caption')
            ?? data_get($post, 'body')
            ?? data_get($post, 'summary')
            ?? '';

        $text = trim(html_entity_decode(strip_tags((string) $raw), ENT_QUOTES));

        // Collapse repeated whitespace left over from stripped HTML block tags.
        return trim((string) preg_replace('/\s+/', ' ', $text));
    }

    protected function shouldSkip(string $text): bool
    {
        if ($text === '') {
            return false; // pure image posts with no caption text are fine
        }

        foreach (self::IGNORED_CONTENT_PATTERNS as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }
        if ($this->looksNonLatinScript($text)) {
            return true;
        }

        return $this->looksNonEnglish($text);
    }

    /**
     * Catches CJK, Cyrillic, Arabic, etc. — scripts that don't use
     * whitespace between words the way the stopword check below
     * assumes, so this has to run as its own, separate check.
     */
    protected function looksNonLatinScript(string $text): bool
    {
        $letters = preg_replace('/[^\p{L}]/u', '', $text);

        if ($letters === '' || $letters === null) {
            return false;
        }

        $latinLetters = preg_replace('/[^A-Za-z]/', '', $letters);

        return (mb_strlen((string) $latinLetters) / mb_strlen($letters)) < 0.4;
    }

    /**
     * Zero-dependency heuristic, not real language detection: checks
     * what fraction of words are common English function words (the,
     * and, is, of...). Real prose in English reliably scores high on
     * this; other Latin-alphabet languages (German, French, Spanish...)
     * reliably score near zero, since they use entirely different words
     * for the same grammatical roles. Short text is left alone since
     * there isn't enough signal to judge reliably either way.
     */
    protected function looksNonEnglish(string $text): bool
    {
        $words = preg_split('/\s+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY);

        if (count($words) < 6) {
            return false;
        }

        static $englishStopwords = [
            'the', 'and', 'is', 'of', 'to', 'a', 'in', 'for', 'with',
            'on', 'this', 'that', 'it', 'was', 'are', 'my', 'i',
            'you', 'we', 'have', 'has', 'be', 'at', 'from', 'or',
        ];

        $matches = count(array_intersect($words, $englishStopwords));

        return ($matches / count($words)) < 0.08;
    }
}
