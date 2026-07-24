<?php

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
        $publicKey = config('services.discord.public_key');

        if (!$signature || !$timestamp || !$publicKey) {
            return false;
        }

        $message = $timestamp . $request->getContent();

        return sodium_crypto_sign_verify_detached(
            sodium_hex2bin($signature),
            $message,
            sodium_hex2bin($publicKey)
        );
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
