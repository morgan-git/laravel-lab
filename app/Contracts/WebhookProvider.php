<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\FeedPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

interface WebhookProvider
{
    public function verify(Request $request): bool;

    public function respond(array $payload): JsonResponse;

    public function formatPayload(?FeedPost $post, string $topic): array;
}
