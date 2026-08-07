<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\FeedProvider;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use Illuminate\Support\Collection;

class BlueSkyService implements FeedProvider
{
    protected Client $client;

    private const string BASE_URL = 'https://public.api.bsky.app/xrpc/';

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?? new Client([
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.9',
                'Referer' => 'https://www.bluesky.com/',
            ],
        ]);
    }

    public function fetch(string $handle): Collection
    {
        try {

            $did = $this->resolveDid($handle);

            if (! $did) {
                return collect();
            }

            $response = $this->client->get(
                self::BASE_URL.'app.bsky.feed.getAuthorFeed',
                [
                    'query' => [
                        'actor' => $did,
                        'limit' => 50,
                    ],
                ]
            );

            if ($response->getStatusCode() !== 200) {
                echo "Error fetching feed for {$handle}";

                return collect();
            }

            $json = json_decode(
                (string) $response->getBody(),
                true,
                flags: JSON_THROW_ON_ERROR
            );

            return $this->parseFeed($json);

        } catch (ClientException $e) {

            if ($e->getResponse()->getStatusCode() === 429) {
                return collect(['throttled' => true]);
            }

            return collect();

        } catch (ServerException|\Throwable) {

            return collect();

        }
    }

    protected function resolveDid(string $handle): ?string
    {
        $response = $this->client->get(
            self::BASE_URL.'com.atproto.identity.resolveHandle',
            [
                'query' => [
                    'handle' => $handle,
                ],
            ]
        );

        if ($response->getStatusCode() !== 200) {
            return null;
        }

        $json = json_decode(
            (string) $response->getBody(),
            true,
            flags: JSON_THROW_ON_ERROR
        );

        return $json['did'] ?? null;
    }

    protected function parseFeed(array $json): Collection
    {
        return collect($json['feed'] ?? [])
            ->map(fn ($entry) => [

                'id' => data_get($entry, 'post.uri'),

                'title' => str(
                    data_get($entry, 'post.record.text')
                )->limit(120)->toString(),

                'url' => $this->postUrl($entry),

                'author' => data_get($entry, 'post.author.handle'),

                'updated' => data_get($entry, 'post.record.createdAt'),

                'content' => data_get($entry, 'post.record.text'),

                'image' => $this->extractImage($entry),

            ]);
    }

    protected function extractImage(array $entry): ?string
    {
        return data_get(
            $entry,
            'post.embed.images.0.fullsize'
        );
    }

    protected function postUrl(array $entry): string
    {
        $handle = data_get($entry, 'post.author.handle');
        $uri = data_get($entry, 'post.uri');

        $parts = explode('/', (string) $uri);

        $rkey = end($parts);

        return "https://bsky.app/profile/{$handle}/post/{$rkey}";
    }
}
