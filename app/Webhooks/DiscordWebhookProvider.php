<?php

declare(strict_types=1);

namespace App\Webhooks;

use App\Contracts\WebhookProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiscordWebhookProvider implements WebhookProvider
{
    public function verify(Request $request): bool
{
    $signature = $request->header('X-Signature-Ed25519');
    $timestamp = $request->header('X-Signature-Timestamp');

    if (! $signature || ! $timestamp) {
        return false;
    }

    $publicKey = hex2bin(config('services.discord.public_key'));
    $signatureBytes = hex2bin($signature);

    if ($signatureBytes === false || strlen($signatureBytes) !== SODIUM_CRYPTO_SIGN_BYTES) {
        return false;
    }

    try {
        return sodium_crypto_sign_verify_detached(
            $signatureBytes,
            $timestamp.$request->getContent(),
            $publicKey
        );
    } catch (\SodiumException) {
        return false;
    }
}

    public function respond(mixed $data): JsonResponse
    {
        // Type 4 = immediate response with message
        return response()->json([
            'type' => 4,
            'data' => [
                'content' => $data,
            ],
        ]);
    }
}
