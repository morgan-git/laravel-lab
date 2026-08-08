<?php

use App\Models\FeedPost;
use App\Models\FeedSource;
use App\Models\WebhookRequest;
use App\Services\FeedSelector;

beforeEach(function () {
    $this->endpoint = '/api/webhook/discord';

    $keypair = sodium_crypto_sign_keypair();
    $this->publicKey = sodium_crypto_sign_publickey($keypair);
    $this->secretKey = sodium_crypto_sign_secretkey($keypair);

    config([
        'services.discord.public_key' => bin2hex($this->publicKey),
    ]);
});

function signedHeaders(string $secretKey, string $body): array
{
    $timestamp = (string) time();

    $signature = bin2hex(
        sodium_crypto_sign_detached($timestamp.$body, $secretKey)
    );

    return [
        'X-Signature-Ed25519' => $signature,
        'X-Signature-Timestamp' => $timestamp,
    ];
}

function discordRequestHeaders(array $headers): array
{
    return [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_SIGNATURE_ED25519' => $headers['X-Signature-Ed25519'],
        'HTTP_X_SIGNATURE_TIMESTAMP' => $headers['X-Signature-Timestamp'],
    ];
}

function feedPostPayload(): array
{
    return [
        'external_id' => 'abc123',
        'title' => 'A very good meme',
        'url' => 'https://example.com/post/abc123',
        'author' => 'some_author',
        'image_url' => null,
        'content' => 'meme content here',
        'posted_at' => now(),
    ];
}

it('returns 401 when the signature is invalid', function () {
    $body = json_encode(['type' => 1]);

    $response = $this->call(
        'POST',
        $this->endpoint,
        [],
        [],
        [],
        [
            'HTTP_X_SIGNATURE_ED25519' => 'deadbeef',
            'HTTP_X_SIGNATURE_TIMESTAMP' => (string) time(),
            'CONTENT_TYPE' => 'application/json',
        ],
        $body
    );

    $response->assertStatus(401);
});

it('logs an unauthorized status when signature verification fails', function () {
    $body = json_encode(['type' => 1]);

    $this->call(
        'POST',
        $this->endpoint,
        [],
        [],
        [],
        [
            'HTTP_X_SIGNATURE_ED25519' => 'deadbeef',
            'HTTP_X_SIGNATURE_TIMESTAMP' => (string) time(),
            'CONTENT_TYPE' => 'application/json',
        ],
        $body
    );

    expect(
        WebhookRequest::where('provider', 'discord')
            ->where('status', 'unauthorized')
            ->exists()
    )->toBeTrue();
});

it('responds to a Discord ping with type 1', function () {
    $body = json_encode(['type' => 1]);
    $headers = signedHeaders($this->secretKey, $body);

    $response = $this->call(
        'POST',
        $this->endpoint,
        [],
        [],
        [],
        discordRequestHeaders($headers),
        $body
    );

    $response->assertStatus(200)
        ->assertJson(['type' => 1]);
});

it('logs a webhook request row and completes it for a valid ping', function () {
    $body = json_encode(['type' => 1]);
    $headers = signedHeaders($this->secretKey, $body);

    $this->call(
        'POST',
        $this->endpoint,
        [],
        [],
        [],
        discordRequestHeaders($headers),
        $body
    );

    expect(
        WebhookRequest::where('provider', 'discord')->count()
    )->toBe(1);

    expect(
        WebhookRequest::where('provider', 'discord')
            ->where('status', 'pending')
            ->count()
    )->toBe(0);
});

it('logs success when a valid topic command finds a post', function () {
    $source = FeedSource::factory()->topic('memes')->create([
        'provider' => 'provider-a',
        'handle' => 'memes',
        'visible' => true,
    ]);

    $source->posts()->create(feedPostPayload());

    $body = json_encode([
        'type' => 2,
        'data' => [
            'name' => 'meme',
            'options' => [
                ['name' => 'topic', 'value' => 'memes'],
            ],
        ],
        'member' => ['user' => ['id' => '123456789']],
    ]);

    $headers = signedHeaders($this->secretKey, $body);

    $response = $this->call(
        'POST',
        $this->endpoint,
        [],
        [],
        [],
        discordRequestHeaders($headers),
        $body
    );

    $response->assertStatus(200);

    expect(
        WebhookRequest::where('provider', 'discord')
            ->where('status', 'success')
            ->exists()
    )->toBeTrue();
});

it('selects a post by topic without requiring the Discord command to know the provider', function () {
    $source = FeedSource::factory()->topic('foodporn')->create([
        'provider' => 'provider-b',
        'handle' => 'foodporn',
        'visible' => true,
    ]);

    $source->posts()->create([
        'external_id' => 'food-123',
        'title' => 'Something delicious',
        'url' => 'https://example.com/food-123',
        'author' => 'feed-user',
        'image_url' => null,
        'content' => 'food content',
        'posted_at' => now(),
    ]);

    $body = json_encode([
        'type' => 2,
        'data' => [
            'name' => 'feed',
            'options' => [
                ['name' => 'topic', 'value' => 'foodporn'],
            ],
        ],
        'member' => ['user' => ['id' => '123456789']],
    ]);

    $headers = signedHeaders($this->secretKey, $body);

    $response = $this->call(
        'POST',
        $this->endpoint,
        [],
        [],
        [],
        discordRequestHeaders($headers),
        $body
    );

    $response->assertStatus(200);

    expect(
        WebhookRequest::where('provider', 'discord')
            ->where('status', 'success')
            ->exists()
    )->toBeTrue();
});

it('does not select posts from hidden sources', function () {
    $source = FeedSource::factory()->topic('foodporn')->create([
        'provider' => 'provider-c',
        'handle' => 'hidden-food',
        'visible' => false,
    ]);

    $source->posts()->create(feedPostPayload());

    $body = json_encode([
        'type' => 2,
        'data' => [
            'name' => 'feed',
            'options' => [
                ['name' => 'topic', 'value' => 'foodporn'],
            ],
        ],
        'member' => ['user' => ['id' => '123456789']],
    ]);

    $headers = signedHeaders($this->secretKey, $body);

    $this->call(
        'POST',
        $this->endpoint,
        [],
        [],
        [],
        discordRequestHeaders($headers),
        $body
    );

    expect(
        WebhookRequest::where('provider', 'discord')
            ->where('status', 'success')
            ->exists()
    )->toBeFalse();
});

it('logs a failed status when no visible source exists for the requested topic', function () {
    $body = json_encode([
        'type' => 2,
        'data' => [
            'name' => 'feed',
            'options' => [
                ['name' => 'topic', 'value' => 'does-not-exist'],
            ],
        ],
        'member' => ['user' => ['id' => '123456789']],
    ]);

    $headers = signedHeaders($this->secretKey, $body);

    $this->call(
        'POST',
        $this->endpoint,
        [],
        [],
        [],
        discordRequestHeaders($headers),
        $body
    );

    expect(
        WebhookRequest::where('provider', 'discord')
            ->where('status', 'failed')
            ->exists()
    )->toBeTrue();
});

it('can select a post from any visible provider for the same topic', function () {
    FeedSource::factory()->topic('foodporn')->create([
        'provider' => 'provider-a',
        'handle' => 'foodporn-a',
        'visible' => true,
    ])->posts()->create(feedPostPayload());

    FeedSource::factory()->topic('foodporn')->create([
        'provider' => 'provider-b',
        'handle' => 'foodporn-b',
        'visible' => true,
    ])->posts()->create(feedPostPayload());

    $post = app(FeedSelector::class)->randomForTopic('foodporn');

    expect($post)->toBeInstanceOf(FeedPost::class);
    expect($post->source->topic->name)->toBe('foodporn');
});
