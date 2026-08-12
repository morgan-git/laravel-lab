<?php

use App\Services\TumblrService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

/**
 * Builds a TumblrService with a mocked Guzzle client that returns the
 * given fixture file's contents as a 200 JSON response.
 */
function makeTumblrService(string $fixtureFile, int $status = 200): TumblrService
{
    $body = file_get_contents(base_path("tests/fixtures/{$fixtureFile}"));

    $mock = new MockHandler([
        new Response($status, ['Content-Type' => 'application/json'], $body),
    ]);

    $handlerStack = HandlerStack::create($mock);
    $client = new Client(['handler' => $handlerStack]);

    return new TumblrService($client);
}

beforeEach(function () {
    config(['services.tumblr.key' => 'test-api-key']);
});

it('extracts posts with a legacy photos array', function () {
    $posts = makeTumblrService('tumblr-mixed.json')->fetch('foodporn');

    $post = $posts->firstWhere('id', '1001');

    expect($post)->not->toBeNull()
        ->and($post['image'])->toBe('https://64.media.tumblr.com/img1001.jpg')
        ->and($post['author'])->toBe('chefmike')
        ->and($post['title'])->toContain('Grilled Salmon');
});

it('falls back to extracting an image from NPF body HTML when there is no photos array', function () {
    $posts = makeTumblrService('tumblr-mixed.json')->fetch('foodporn');

    $post = $posts->firstWhere('id', '1002');

    expect($post)->not->toBeNull()
        ->and($post['image'])->toBe('https://64.media.tumblr.com/img1002.jpg');
});

it('drops posts that have no image in either the photos array or the body HTML', function () {
    $posts = makeTumblrService('tumblr-mixed.json')->fetch('foodporn');

    expect($posts->firstWhere('id', '1005'))->toBeNull();
});

it('deduplicates content-farm reposts that share the same linked article URL', function () {
    $posts = makeTumblrService('tumblr-mixed.json')->fetch('foodporn');

    // Posts 1003 and 1004 share the same link_url (same article,
    // reposted by two sibling blogs) — only the first should survive.
    $pastaPosts = $posts->filter(fn ($post) => str_contains((string) $post['title'], 'Best Pasta'));

    expect($pastaPosts)->toHaveCount(1)
        ->and($pastaPosts->first()['author'])->toBe('reciperoundup-a');
});

it('returns the expected total after filtering and deduping the fixture', function () {
    $posts = makeTumblrService('tumblr-mixed.json')->fetch('foodporn');

    // 5 raw posts in: 1005 has no image (dropped), 1004 is a dupe of 1003
    // (dropped) — 3 should remain: 1001, 1002, 1003.
    expect($posts)->toHaveCount(3);
});

it('returns an empty collection when the API responds with a non-200 status', function () {
    $posts = makeTumblrService('tumblr-mixed.json', status: 500)->fetch('foodporn');

    expect($posts)->toBeEmpty();
});

it('returns an empty collection instead of throwing on malformed JSON', function () {
    $mock = new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], 'not valid json{{{'),
    ]);

    $client = new Client(['handler' => HandlerStack::create($mock)]);
    $service = new TumblrService($client);

    expect($service->fetch('foodporn'))->toBeEmpty();
});

it('sends the tag and api key as query parameters', function () {
    $history = [];
    $body = file_get_contents(base_path('tests/fixtures/tumblr-mixed.json'));

    $mock = new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], $body),
    ]);

    $handlerStack = HandlerStack::create($mock);
    $handlerStack->push(Middleware::history($history));

    $client = new Client(['handler' => $handlerStack]);
    new TumblrService($client)->fetch('foodporn');

    expect($history)->toHaveCount(1);

    parse_str((string) $history[0]['request']->getUri()->getQuery(), $query);

    expect($query['tag'])->toBe('foodporn')
        ->and($query['api_key'])->toBe('test-api-key');
});
