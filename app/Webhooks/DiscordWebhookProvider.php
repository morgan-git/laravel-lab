<?php

declare(strict_types=1);

namespace App\Webhooks;

use App\Contracts\WebhookProvider;
use App\Models\FeedPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiscordWebhookProvider implements WebhookProvider
{
    public function verify(Request $request): bool
    {
        $signature = $request->header('X-Signature-Ed25519');
        $timestamp = $request->header('X-Signature-Timestamp');
        $body = $request->getContent();

        if (! $signature || ! $timestamp) {
            return false;
        }

        // Ensure the signature is a valid hex string before passing to hex2bin
        if (! ctype_xdigit($signature)) {
            return false;
        }

        $publicKeyHex = config('services.discord.public_key');
        if (! $publicKeyHex || ! ctype_xdigit((string) $publicKeyHex)) {
            return false;
        }

        $publicKey = hex2bin((string) $publicKeyHex);
        $signatureBytes = hex2bin($signature);

        if ($signatureBytes === false || strlen($signatureBytes) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return false;
        }

        $message = $timestamp.$body;

        return sodium_crypto_sign_verify_detached($signatureBytes, $message, $publicKey);
    }

    public function isPing(Request $request): bool
    {
        return (int) $request->input('type') === 1;
    }

    public function pingResponse(): JsonResponse
    {
        return response()->json(['type' => 1]);
    }

    public function requesterId(Request $request): string
    {
        return (string) $request->input('guild_id', 'unknown');
    }

    public function requesterType(Request $request): string
    {
        return 'guild';
    }

    public function action(Request $request): string
    {
        return (string) $request->input('data.name', 'unknown');
    }

    public function formatPayload(?FeedPost $post, string $topic): array
    {
        // Handle the "not found" case
        if (! $post instanceof FeedPost) {
            return [
                'content' => "No posts found for \"{$topic}\".",
            ];
        }

        // Build the rich Discord embed for Tumblr/Bluesky data
        $title = $post->title ?: 'View Post';
        if (mb_strlen($title) > 256) {
            $title = mb_substr($title, 0, 253).'...';
        }

        $embed = [
            'title' => $title,
            'color' => 9109686,
        ];

        // Discord can silently reject the whole embed if `url` is present
        // but empty/malformed, so only include it when we actually have
        // one — same guard as image_url below.
        if (! empty($post->url)) {
            $embed['url'] = $post->url;
        }

        if (! empty($post->image_url)) {
            $embed['image'] = [
                'url' => $post->image_url,
            ];
        }

        return [
            'embeds' => [$embed],
        ];
    }

    public function respond(array $payload): JsonResponse
    {
        return response()->json([
            'type' => 4,
            'data' => $payload,
        ]);
    }
}
