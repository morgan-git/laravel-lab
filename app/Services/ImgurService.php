<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\FeedProvider;
use GuzzleHttp\Client;
use Illuminate\Support\Collection;

class ImgurService implements FeedProvider
{
    protected Client $client;

    protected string $clientId;

    /**
     * Off-topic "thanks for upvotes" / follow-spam style comments or descriptions
     * that sometimes slip into viral images. Not exhaustive — just the common
     * patterns worth auto-filtering.
     */
    private const array IGNORED_CONTENT_PATTERNS = [
        '/thanks?(?:\s+\w+){0,4}?\s+for\s+(the\s+)?(upvotes?|likes?|front\s*page)/i',
        '/edit:\s*thanks/i',
        '/send\s+(\w+\s+)?nudes/i',
        '/check out my (profile|link|shop)/i',
    ];

    /**
     * Content-farm or serial reposter patterns matching short label prefixes
     * to keep titles standardized across both providers.
     */
    private const string KNOWN_PREFIX_PATTERN = '/^(Weeknight dinner solved:\s*|This one\'s a keeper:\s*|I\'ve been making this on repeat:\s*|Saving this one for later:\s*)/i';

    /**
     * Specific Imgur user accounts to block entirely.
     */
    private const array BLOCKED_ACCOUNT_PREFIXES = [
        'optimalrecipes',
    ];

    public function __construct(?Client $client = null)
    {
        $this->clientId = config('services.imgur.client_id');

        $this->client = $client ?? new Client([
            'headers' => [
                'Authorization' => 'Client-ID '.$this->clientId,
                'Accept' => 'application/json',
            ],
        ]);
    }

    /**
     * Fetch public gallery items matching a tag (e.g., "memes" or "foodporn")
     */
    public function fetch(string $tag): Collection
    {
        try {
            // Imgur API tags endpoint structure: /3/gallery/t/{tag}/{sort}/{window}/{page}
            $cleanTag = trim($tag, '#/');
            $response = $this->client->get("https://imgur.com{$cleanTag}/viral/week/0");

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
        // Imgur encapsulates tagged gallery items inside data.items
        $items = data_get($json, 'data.items', []);

        return collect($items)
            ->map(function ($item): ?array {
                $accountName = (string) data_get($item, 'account_url', '');

                if ($this->isBlockedAccount($accountName)) {
                    return null;
                }

                $imageUrl = $this->extractImage($item);

                if (! $imageUrl) {
                    return null;
                }

                $text = $this->extractText($item);

                if ($this->shouldSkip($text)) {
                    return null;
                }

                $rawTitle = $text !== '' ? $text : (data_get($item, 'title') ?: 'Meme Post');

                // Strip out dynamic content-farm prefixes for a clean title
                $title = trim(preg_replace(self::KNOWN_PREFIX_PATTERN, '', $rawTitle));
                if ($title === '') {
                    $title = 'Meme Post';
                }

                return [
                    'id' => (string) data_get($item, 'id'),
                    'title' => $title,
                    'url' => data_get($item, 'link'),
                    'author' => data_get($item, 'account_url') ?: 'imgur_user',
                    'updated' => date('Y-m-d H:i:s', data_get($item, 'datetime', time())),
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

        $clean = preg_replace(self::KNOWN_PREFIX_PATTERN, '', $text);
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

    protected function isBlockedAccount(string $accountName): bool
    {
        $accountName = mb_strtolower($accountName);

        return array_any(self::BLOCKED_ACCOUNT_PREFIXES, fn ($prefix) => str_starts_with($accountName, mb_strtolower((string) $prefix)));
    }

    protected function extractImage(array $item): ?string
    {
        // If it's an album, extract the first image in the collection
        if ((bool) data_get($item, 'is_album', false)) {
            $firstImage = data_get($item, 'images.0');
            if ($firstImage) {
                return $this->getDirectLink($firstImage);
            }
        }

        return $this->getDirectLink($item);
    }

    private function getDirectLink(array $imageObj): ?string
    {
        // Avoid serving raw mp4/webms directly unless your bot specifically handles them
        if (str_contains((string) data_get($imageObj, 'type', ''), 'video')) {
            // Fallback to an animated GIF thumbnail variant if available, or its static link
            return data_get($imageObj, 'gifv') ?? data_get($imageObj, 'link');
        }

        return data_get($imageObj, 'link');
    }

    protected function extractText(array $item): string
    {
        // Imgur titles often serve as the main context; description is fallback text
        $raw = data_get($item, 'description')
            ?? data_get($item, 'title')
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
        // Match the structural return array requirement left open in original snippet
        return false;
    }
}
