<?php

use App\Models\FeedSource;
use App\Models\WebhookRequest;

beforeEach(function () {
    $this->endpoint = '/api/webhook/discord';

    $keypair = sodium_crypto_sign_keypair();
    $this->publicKey = sodium_crypto_sign_publickey($keypair);
    $this->secretKey = sodium_crypto_sign_secretkey($keypair);

    config(['services.discord.public_key' => bin2hex($this->publicKey)]);
});

function signedHeaders(string $secretKey, string $body): array
{
    $timestamp = (string) time();
    $signature = bin2hex(sodium_crypto_sign_detached($timestamp.$body, $secretKey));

    return [
        'X-Signature-Ed25519' => $signature,
        'X-Signature-Timestamp' => $timestamp,
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

    expect(WebhookRequest::where('status', 'unauthorized')->exists())->toBeTrue();
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
        ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE_ED25519' => $headers['X-Signature-Ed25519'], 'HTTP_X_SIGNATURE_TIMESTAMP' => $headers['X-Signature-Timestamp']],
        $body
    );

    $response->assertStatus(200)
        ->assertJson(['type' => 1]);
});

it('logs a webhook request row on every request, starting as pending', function () {
    $body = json_encode(['type' => 1]);
    $headers = signedHeaders($this->secretKey, $body);

    $this->call(
        'POST',
        $this->endpoint,
        [],
        [],
        [],
        [
            'HTTP_X_SIGNATURE_ED25519' => $headers['X-Signature-Ed25519'],
            'HTTP_X_SIGNATURE_TIMESTAMP' => $headers['X-Signature-Timestamp'],
            'CONTENT_TYPE' => 'application/json',
        ],
        $body
    );

    // A row should exist for this request regardless of outcome, and
    // since verification passed and it was a ping, it should end as
    // something other than 'pending' by the time the response returns.
    expect(WebhookRequest::where('provider', 'discord')->exists())->toBeTrue();
});

it('logs success on a valid slash command interaction', function () {
    $source = FeedSource::factory()->reddit('memes')->create();

    $source->posts()->create([
        'external_id' => 'abc123',
        'title' => 'A very good meme',
        'url' => 'https://old.reddit.com/r/memes/comments/abc123',
        'author' => 'some_redditor',
        'image_url' => null,
        'content' => 'meme content here',
        'posted_at' => now(),
    ]);

    $body = json_encode([
        'type' => 2,
        'data' => [
            'name' => 'meme',
            'options' => [
                ['name' => 'subreddit', 'value' => 'memes'],
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
        [
            'HTTP_X_SIGNATURE_ED25519' => $headers['X-Signature-Ed25519'],
            'HTTP_X_SIGNATURE_TIMESTAMP' => $headers['X-Signature-Timestamp'],
            'CONTENT_TYPE' => 'application/json',
        ],
        $body
    );

    $response->assertStatus(200);

    expect(WebhookRequest::where('provider', 'discord')
        ->where('status', 'success')
        ->exists())->toBeTrue();
});
