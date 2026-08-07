<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\FeedProvider;
use GuzzleHttp\Client;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TumblrService implements FeedProvider
{
    protected Client $client;

    protected string $apiKey;

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?? new Client([
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        // Put your consumer key in your .env file (e.g., TUMBLR_API_KEY=your_key_here)
        $this->apiKey = config('services.tumblr.key', env('TUMBLR_API_KEY'));
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

                $postUrl = data_get($post, 'post_url');
                $summary = data_get($post, 'summary', data_get($post, 'slug', 'Food Post'));

                return [
                    'id' => (string) data_get($post, 'id'),
                    'title' => Str::limit($summary, 120),
                    'url' => $postUrl,
                    'author' => data_get($post, 'blog_name', 'tumblr'),
                    'updated' => date('Y-m-d H:i:s', data_get($post, 'timestamp', time())),
                    'content' => $summary,
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
}
