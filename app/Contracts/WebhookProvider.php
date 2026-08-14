<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\FeedPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

interface WebhookProvider
{
    public function verify(Request $request): bool;

    /**
     * Whether this request is the platform's own handshake/health-check
     * ping, distinct from a real command. Discord's is a {"type": 1}
     * body; other platforms may use a header, a different field, or not
     * have this concept at all (in which case, just always return false).
     */
    public function isPing(Request $request): bool;

    /**
     * The response to a ping, as opposed to a real command response.
     * Kept separate from respond() because a ping's expected shape is
     * often not the same "wrap this payload" shape a normal response
     * uses — Discord's ping reply is a bare {"type": 1}, not wrapped in
     * the {"type": 4, "data": ...} shape respond() produces.
     */
    public function pingResponse(): JsonResponse;

    /**
     * A stable identifier for whoever/wherever sent this request, for
     * logging — Discord's is guild_id, another platform's might be a
     * team ID, channel ID, or something else entirely.
     */
    public function requesterId(Request $request): string;

    /**
     * A short label for what kind of requester requesterId() refers to
     * (e.g. "guild") — purely descriptive, for the log.
     */
    public function requesterType(Request $request): string;

    /**
     * The topic being requested, extracted from wherever this platform
     * puts it — Discord's is the slash command name (data.name); a
     * different platform might use a query param, a slug in the URL
     * path, or an option value instead.
     */
    public function action(Request $request): string;

    public function formatPayload(?FeedPost $post, string $topic): array;

    public function respond(array $payload): JsonResponse;
}
