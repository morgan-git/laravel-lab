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
        '/thanks?(?:\s+\w+){0,4}?\s+for\s+(the\s+)?(likes?|follows?|reblogs?)/i',
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
                    'limit' => 20,
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

                $rawTitle = $text !== '' ? $text : (data_get($post, 'slug') ?: 'Food Post');

                // Strip out dynamic content-farm prefixes for a clean title
                $title = trim(preg_replace('/^(Weeknight dinner solved:\s*|This one\'s a keeper:\s*)/i', '', $rawTitle));
                if ($title === '') {
                    $title = 'Food Post';
                }

                return [
                    'id' => (string) data_get($post, 'id'),
                    'title' => $title,
                    'url' => data_get($post, 'post_url'),
                    'author' => data_get($post, 'blog_name', 'tumblr'),
                    'updated' => date('Y-m-d H:i:s', data_get($post, 'timestamp', time())),
                    'content' => $text,
                    'image' => $imageUrl,
                    'dedupe_key' => $this->generateDedupeKey($text, $imageUrl),
                ];
            })
            ->filter()
            ->unique('dedupe_key')
            ->values();
    }

    protected function generateDedupeKey(string $text, ?string $imageUrl): string
    {
        if ($text === '') {
            return $this->normalizeUrl($imageUrl) ?? uniqid();
        }

        $clean = preg_replace('/^(Weeknight dinner solved:\s*|This one\'s a keeper:\s*)/i', '', $text);
        $clean = preg_replace('/(You might also love:.*)$/i', '', (string) $clean);

        $clean = mb_strtolower((string) preg_replace('/[^\p{L}\p{N}\s]/u', '', (string) $clean));

        $words = preg_split('/\s+/u', $clean, -1, PREG_SPLIT_NO_EMPTY);
        $words = array_diff($words, ['you', 'might', 'also', 'love', 'recipe', 'this', 'ones', 'a', 'keeper', 'weeknight', 'dinner', 'solved']);

        sort($words);

        if (count($words) >= 3) {
            return md5(implode(' ', $words));
        }

        return md5($clean);
    }

    protected function normalizeUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $clean = strtok($url, '?#');

        return strtolower(rtrim((string) $clean, '/'));
    }

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

    protected function extractText(array $post): string
    {
        $raw = data_get($post, 'caption')
            ?? data_get($post, 'body')
            ?? data_get($post, 'summary')
            ?? '';

        $text = trim(html_entity_decode(strip_tags((string) $raw), ENT_QUOTES));

        return trim((string) preg_replace('/\s+/', ' ', $text));
    }

    protected function shouldSkip(string $text): bool
    {
        if ($text === '') {
            return false;
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

    protected function looksNonLatinScript(string $text): bool
    {
        $letters = preg_replace('/[^\p{L}]/u', '', $text);

        if ($letters === '' || $letters === null) {
            return false;
        }

        $latinLetters = preg_replace('/[^A-Za-z]/', '', $letters);

        return (mb_strlen((string) $latinLetters) / mb_strlen($letters)) < 0.4;
    }

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
