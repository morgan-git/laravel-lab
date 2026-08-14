<?php

use App\Models\FeedPost;
use App\Webhooks\DiscordWebhookProvider;
use Illuminate\Http\Request;

beforeEach(function () {
    // Generate a fresh Ed25519 keypair for each test so we're not
    // dependent on a real Discord app during CI.
    $this->keypair = sodium_crypto_sign_keypair();
    $this->publicKey = sodium_crypto_sign_publickey($this->keypair);
    $this->secretKey = sodium_crypto_sign_secretkey($this->keypair);

    config(['services.discord.public_key' => bin2hex($this->publicKey)]);

    $this->provider = new DiscordWebhookProvider;

    // Bound closure rather than a global function — WebhookControllerTest
    // uses the same approach for its request-building helper, specifically
    // to avoid two test files declaring a same-named global function and
    // fatal-erroring the whole suite when both load in one Pest run.
    $this->jsonRequest = (fn (array $payload) => Request::create(
        '/webhook/discord',
        'POST',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json'],
        json_encode($payload)
    ));
});

function signBody(string $secretKey, string $timestamp, string $body): string
{
    $message = $timestamp.$body;
    $signature = sodium_crypto_sign_detached($message, $secretKey);

    return bin2hex($signature);
}

// --- verify() ---------------------------------------------------------

it('verifies a request with a valid signature', function () {
    $timestamp = (string) time();
    $body = json_encode(['type' => 1]);
    $signature = signBody($this->secretKey, $timestamp, $body);

    $request = Request::create('/webhook/discord', 'POST', [], [], [], [
        'HTTP_X_SIGNATURE_ED25519' => $signature,
        'HTTP_X_SIGNATURE_TIMESTAMP' => $timestamp,
    ], $body);

    expect($this->provider->verify($request))->toBeTrue();
});

it('rejects a request with a bad signature', function () {
    $timestamp = (string) time();
    $body = json_encode(['type' => 1]);

    // Sign with a *different* keypair so the signature doesn't match
    // the configured public key.
    $otherKeypair = sodium_crypto_sign_keypair();
    $otherSecret = sodium_crypto_sign_secretkey($otherKeypair);
    $badSignature = signBody($otherSecret, $timestamp, $body);

    $request = Request::create('/webhook/discord', 'POST', [], [], [], [
        'HTTP_X_SIGNATURE_ED25519' => $badSignature,
        'HTTP_X_SIGNATURE_TIMESTAMP' => $timestamp,
    ], $body);

    expect($this->provider->verify($request))->toBeFalse();
});

it('rejects a request with a tampered body', function () {
    $timestamp = (string) time();
    $originalBody = json_encode(['type' => 1]);
    $signature = signBody($this->secretKey, $timestamp, $originalBody);

    // Signature was generated for $originalBody, but the request
    // carries different content.
    $tamperedBody = json_encode(['type' => 1, 'injected' => true]);

    $request = Request::create('/webhook/discord', 'POST', [], [], [], [
        'HTTP_X_SIGNATURE_ED25519' => $signature,
        'HTTP_X_SIGNATURE_TIMESTAMP' => $timestamp,
    ], $tamperedBody);

    expect($this->provider->verify($request))->toBeFalse();
});

it('rejects a request missing the signature header', function () {
    $timestamp = (string) time();
    $body = json_encode(['type' => 1]);

    $request = Request::create('/webhook/discord', 'POST', [], [], [], [
        'HTTP_X_SIGNATURE_TIMESTAMP' => $timestamp,
    ], $body);

    expect($this->provider->verify($request))->toBeFalse();
});

it('rejects a request missing the timestamp header', function () {
    $body = json_encode(['type' => 1]);
    $signature = signBody($this->secretKey, (string) time(), $body);

    $request = Request::create('/webhook/discord', 'POST', [], [], [], [
        'HTTP_X_SIGNATURE_ED25519' => $signature,
    ], $body);

    expect($this->provider->verify($request))->toBeFalse();
});

it('rejects a request with a malformed (non-hex) signature', function () {
    $timestamp = (string) time();
    $body = json_encode(['type' => 1]);

    $request = Request::create('/webhook/discord', 'POST', [], [], [], [
        'HTTP_X_SIGNATURE_ED25519' => 'not-a-real-signature',
        'HTTP_X_SIGNATURE_TIMESTAMP' => $timestamp,
    ], $body);

    expect($this->provider->verify($request))->toBeFalse();
});

// --- isPing() / pingResponse() -----------------------------------------

it('identifies a type 1 interaction as a ping', function () {
    $request = ($this->jsonRequest)(['type' => 1]);

    expect($this->provider->isPing($request))->toBeTrue();
});

it('does not identify a command interaction as a ping', function () {
    $request = ($this->jsonRequest)([
        'type' => 2,
        'data' => ['name' => 'foodporn'],
    ]);

    expect($this->provider->isPing($request))->toBeFalse();
});

it('returns the bare {type: 1} shape for a ping response, not the wrapped command shape', function () {
    $response = $this->provider->pingResponse();

    expect($response->status())->toBe(200)
        ->and($response->getData(true))->toBe(['type' => 1]);
});

// --- requesterId() / requesterType() -------------------------------------

it('extracts the guild id as the requester id', function () {
    $request = ($this->jsonRequest)(['guild_id' => 'guild-456']);

    expect($this->provider->requesterId($request))->toBe('guild-456');
});

it('falls back to "unknown" when no guild id is present', function () {
    $request = ($this->jsonRequest)(['type' => 2]);

    expect($this->provider->requesterId($request))->toBe('unknown');
});

it('always reports "guild" as the requester type', function () {
    // requesterType() doesn't currently inspect the request at all, but
    // it takes one per the contract — passing an empty request confirms
    // that stays true rather than the method secretly depending on
    // something like guild_id being present.
    $request = ($this->jsonRequest)([]);

    expect($this->provider->requesterType($request))->toBe('guild');
});

// --- action() -----------------------------------------------------------

it('extracts the slash command name as the action/topic', function () {
    $request = ($this->jsonRequest)([
        'type' => 2,
        'data' => ['name' => 'foodporn'],
    ]);

    expect($this->provider->action($request))->toBe('foodporn');
});

it('falls back to "unknown" when no command name is present', function () {
    $request = ($this->jsonRequest)(['type' => 1]);

    expect($this->provider->action($request))->toBe('unknown');
});

// --- respond() ------------------------------------------------------

it('responds with the required Discord JSON shape', function () {
    $response = $this->provider->respond(['content' => 'hello from labbotameme']);

    expect($response->status())->toBe(200);

    $data = $response->getData(true);

    expect($data)->toHaveKey('type')
        ->and($data['type'])->toBe(4)
        ->and($data)->toHaveKey('data')
        ->and($data['data']['content'])->toBe('hello from labbotameme');
});

// --- formatPayload() --------------------------------------------------

it('formats a payload correctly with a post, image, and safe title', function () {
    $post = new FeedPost([
        'title' => 'Test Post Title',
        'url' => 'https://example.com/post',
        'image_url' => 'https://example.com/image.jpg',
    ]);

    $payload = $this->provider->formatPayload($post, 'foodporn');

    expect($payload)->toHaveKey('embeds')
        ->and($payload['embeds'][0])->toMatchArray([
            'title' => 'Test Post Title',
            'url' => 'https://example.com/post',
            'color' => 9109686,
            'image' => [
                'url' => 'https://example.com/image.jpg',
            ],
        ]);
});

it('formats a fallback error payload when no post is found', function () {
    $payload = $this->provider->formatPayload(null, 'foodporn');

    expect($payload)->toHaveKey('content')
        ->and($payload['content'])->toBe('No posts found for "foodporn".');
});

it('truncates overly long titles to satisfy Discord limits', function () {
    $longTitle = str_repeat('A', 300); // 300 characters, over the 256 limit

    $post = new FeedPost([
        'title' => $longTitle,
        'url' => 'https://example.com/post',
    ]);

    $payload = $this->provider->formatPayload($post, 'foodporn');

    expect(mb_strlen((string) $payload['embeds'][0]['title']))->toBeLessThanOrEqual(256)
        ->and($payload['embeds'][0]['title'])->toEndWith('...');
});
