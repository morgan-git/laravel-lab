<?php

use App\Webhooks\DiscordWebhookProvider;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| Assumptions (adjust to match your actual class if different):
|--------------------------------------------------------------------------
| - DiscordWebhookProvider is instantiated with no constructor args, and
|   reads the public key from config('services.discord.public_key') at
|   call time (same place your .env DISCORD_PUBLIC_KEY feeds into).
| - verify(Request $request) reads the raw body via $request->getContent(),
|   plus 'X-Signature-Ed25519' and 'X-Signature-Timestamp' headers.
| - respond(mixed $data) returns a JsonResponse with at least a 'type' key.
|
| If your real signatures differ (e.g. constructor takes the key directly),
| just tell me and I'll adjust — the crypto/test logic below stays the same.
*/

beforeEach(function () {
    // Generate a fresh Ed25519 keypair for each test so we're not
    // dependent on a real Discord app during CI.
    $this->keypair = sodium_crypto_sign_keypair();
    $this->publicKey = sodium_crypto_sign_publickey($this->keypair);
    $this->secretKey = sodium_crypto_sign_secretkey($this->keypair);

    config(['services.discord.public_key' => bin2hex($this->publicKey)]);

    $this->provider = new DiscordWebhookProvider();
});

function signBody(string $secretKey, string $timestamp, string $body): string
{
    $message = $timestamp.$body;
    $signature = sodium_crypto_sign_detached($message, $secretKey);

    return bin2hex($signature);
}

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

it('responds with the required Discord JSON shape', function () {
    $response = $this->provider->respond(['content' => 'hello from labbotameme']);

    expect($response->status())->toBe(200);

    $data = $response->getData(true);

    expect($data)->toHaveKey('type')
        ->and($data['type'])->toBe(4)
        ->and($data)->toHaveKey('data')
        ->and($data['data']['content'])->toBe('hello from labbotameme');
});
