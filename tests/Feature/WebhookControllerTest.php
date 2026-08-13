<?php

declare(strict_types=1);

use App\Models\FeedPost;
use App\Models\FeedSource;
use App\Models\Topic;
use App\Models\WebhookRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ASSUMPTION: the Discord webhook route is POST /api/webhook/discord, per
// the ngrok Interactions Endpoint URL used earlier in this project
// (.../api/webhook/discord). Adjust the DISCORD_ENDPOINT constant below if
// the actual route path/name differs.
//
// ASSUMPTION: FeedPost::feedSource() is the inverse relation name for
// FeedSource::posts() / hasMany. If it's named differently, only the
// factory calls below (->for($source, 'source')) need updating.
const DISCORD_ENDPOINT = '/api/webhook/discord';

beforeEach(function () {
    // Fresh Ed25519 keypair per test, same approach as
    // DiscordWebhookProviderTest, so we're not dependent on a real
    // Discord app during CI.
    $keypair = sodium_crypto_sign_keypair();
    $this->publicKey = sodium_crypto_sign_publickey($keypair);
    $this->secretKey = sodium_crypto_sign_secretkey($keypair);

    config(['services.discord.public_key' => bin2hex($this->publicKey)]);

    // Local signing helper as a bound closure rather than a global
    // function — a global `signBody()` here would collide with the one
    // already declared in DiscordWebhookProviderTest.php and fatal-error
    // the whole suite the moment both files load in the same run.
    $this->sign = function (string $timestamp, string $body): string {
        $message = $timestamp.$body;

        return bin2hex(sodium_crypto_sign_detached($message, $this->secretKey));
    };

    $this->postDiscord = function (array $payload) {
        $body = json_encode($payload);
        $timestamp = (string) time();
        $signature = ($this->sign)($timestamp, $body);

        return $this->call(
            'POST',
            DISCORD_ENDPOINT,
            [],
            [],
            [],
            [
                'HTTP_X_SIGNATURE_ED25519' => $signature,
                'HTTP_X_SIGNATURE_TIMESTAMP' => $timestamp,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            $body
        );
    };
});

// --- Signature verification at the HTTP layer -----------------------------

it('rejects a request with an invalid signature and logs it unauthorized', function () {
    $body = json_encode(['type' => 1]);

    $response = $this->call(
        'POST',
        DISCORD_ENDPOINT,
        [],
        [],
        [],
        [
            'HTTP_X_SIGNATURE_ED25519' => bin2hex(random_bytes(64)),
            'HTTP_X_SIGNATURE_TIMESTAMP' => (string) time(),
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ],
        $body
    );

    $response->assertStatus(401)->assertJson(['error' => 'Invalid signature']);

    $log = WebhookRequest::latest('id')->first();
    expect($log->provider)->toBe('discord')
        ->and($log->status)->toBe('unauthorized');
});

// --- Ping handling ------------------------------------------------------

it('responds to a Discord ping and logs it', function () {
    $response = ($this->postDiscord)(['type' => 1]);

    $response->assertOk()->assertJson(['type' => 1]);

    $log = WebhookRequest::latest('id')->first();
    expect($log->status)->toBe('ping')
        ->and($log->payload_out)->toBe(['type' => 1]);
});

// --- Command handling: post found ----------------------------------------

it('returns a formatted embed and logs success when a post exists for the topic', function () {
    $topic = Topic::factory()->create(['name' => 'foodporn']);
    $source = FeedSource::factory()->for($topic, 'topic')->create(['provider' => 'tumblr']);
    $post = FeedPost::factory()->for($source, 'source')->create([
        'title' => 'Cheeseburgers, Fries and Onion Rings',
        'url' => 'https://yummyfoooooood.tumblr.com/post/824763574529523712',
        'image_url' => 'https://64.media.tumblr.com/example.jpg',
    ]);

    $response = ($this->postDiscord)([
        'type' => 2,
        'guild_id' => 'guild-123',
        'data' => ['name' => 'foodporn'],
    ]);

    $response->assertOk();
    $response->assertJson([
        'type' => 4,
        'data' => [
            'embeds' => [
                [
                    'title' => $post->title,
                    'url' => $post->url,
                    'color' => 9109686,
                    'image' => ['url' => $post->image_url],
                ],
            ],
        ],
    ]);

    $log = WebhookRequest::latest('id')->first();
    expect($log->provider)->toBe('discord')
        ->and($log->requester_id)->toBe('guild-123')
        ->and($log->requester_type)->toBe('guild')
        ->and($log->action)->toBe('foodporn')
        ->and($log->status)->toBe('success');
});

it('truncates a long title in the actual HTTP response, not just in the unit test', function () {
    $topic = Topic::factory()->create(['name' => 'foodporn']);
    $source = FeedSource::factory()->for($topic, 'topic')->create();
    FeedPost::factory()->for($source, 'source')->create([
        'title' => str_repeat('A', 300),
    ]);

    $response = ($this->postDiscord)([
        'type' => 2,
        'guild_id' => 'guild-123',
        'data' => ['name' => 'foodporn'],
    ]);

    $title = $response->json('data.embeds.0.title');

    expect(mb_strlen((string) $title))->toBeLessThanOrEqual(256)
        ->and($title)->toEndWith('...');
});

// --- Command handling: no post found -------------------------------------

it('returns the fallback message and logs failed when the topic has no posts', function () {
    Topic::factory()->create(['name' => 'foodporn']);

    $response = ($this->postDiscord)([
        'type' => 2,
        'guild_id' => 'guild-123',
        'data' => ['name' => 'foodporn'],
    ]);

    $response->assertOk();
    $response->assertJson([
        'type' => 4,
        'data' => ['content' => 'No posts found for "foodporn".'],
    ]);

    $log = WebhookRequest::latest('id')->first();
    expect($log->status)->toBe('failed');
});

it('returns the fallback message for a topic that does not exist at all', function () {
    // No Topic row created — simulates a stale/typo'd/deleted slash
    // command still hitting the webhook.
    $response = ($this->postDiscord)([
        'type' => 2,
        'guild_id' => 'guild-123',
        'data' => ['name' => 'not-a-real-topic'],
    ]);

    $response->assertOk();
    $response->assertJson([
        'data' => ['content' => 'No posts found for "not-a-real-topic".'],
    ]);
});

// --- Logging shape --------------------------------------------------------

it('logs the full inbound payload verbatim on payload_in', function () {
    $payload = [
        'type' => 2,
        'guild_id' => 'guild-123',
        'data' => ['name' => 'foodporn'],
    ];

    ($this->postDiscord)($payload);

    $log = WebhookRequest::latest('id')->first();

    expect($log->payload_in)->toMatchArray($payload);
});
