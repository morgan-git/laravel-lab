<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\FeedProvider;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PinterestService implements FeedProvider
{
    protected Client $client;

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?? new Client([
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36',
                'Accept' => 'application/rss+xml, application/xml, text/xml;q=0.9, */*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.9',
            ],
        ]);
    }

    /**
     * Fetch pins from a Pinterest board.
     * Expects handle to be passed in format: "username/board-name" (e.g., "savory_chef/food-crimes")
     */
    public function fetch(string $handle): Collection
    {
        try {
            // Format example: username/board
            $url = 'https://www.pinterest.com/'.trim($handle, '/').'.rss';

            $response = $this->client->get($url);

            if ($response->getStatusCode() !== 200) {
                return collect();
            }

            $xmlString = (string) $response->getBody();

            // Suppress XML warnings for malformed descriptions
            $xml = @simplexml_load_string($xmlString, 'SimpleXMLElement', LIBXML_NOCDATA);

            if ($xml === false || (! property_exists($xml->channel, 'item') || $xml->channel->item === null)) {
                return collect();
            }

            return $this->parseFeed($xml->channel->item);

        } catch (ClientException|ServerException|\Throwable) {
            return collect();
        }
    }

    protected function parseFeed($items): Collection
    {
        $collection = collect();

        foreach ($items as $item) {
            $description = (string) $item->description;
            $imageUrl = $this->extractImageFromDescription($description);

            // Skip items without images if we only want pure visual cards
            if (! $imageUrl) {
                continue;
            }

            $link = (string) $item->link;

            // Extract a clean unique identifier from the pin link
            $pathSegments = explode('/', trim(parse_url($link, PHP_URL_PATH) ?? '', '/'));
            $pinId = end($pathSegments) ?: md5($link);

            $collection->push([
                'id' => $pinId,
                'title' => Str::limit((string) $item->title, 120),
                'url' => $link,
                'author' => parse_url($link, PHP_URL_HOST) ?? 'pinterest.com',
                'updated' => isset($item->pubDate) ? date('Y-m-d H:i:s', strtotime((string) $item->pubDate)) : null,
                'content' => strip_tags($description),
                'image' => $imageUrl,
            ]);
        }

        return $collection;
    }

    protected function extractImageFromDescription(string $description): ?string
    {
        // Pinterest RSS wraps the image inside an <img> tag in the description.
        if (preg_match('/<img[^>]+src="([^">]+)"/', $description, $matches)) {
            $imageUrl = $matches[1];

            // Pinterest thumbnails default to small sizes (e.g., 200x.jpg).
            // Swap it to high-res (originals or 736x) for the modal overlay!
            $imageUrl = str_replace(['/_b.jpg', '/200x/', '/474x/'], ['/_o.jpg', '/736x/', '/736x/'], $imageUrl);

            return $imageUrl;
        }

        return null;
    }
}
