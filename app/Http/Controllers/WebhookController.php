<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\WebhookProvider;
use App\Models\FeedPost;
use App\Models\WebhookRequest;
use App\Services\FeedSelector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    private readonly WebhookProvider $provider;

    public function __construct()
    {
        $this->provider = app(WebhookProvider::class.':discord');
    }

    public function discord(Request $request, FeedSelector $selector): JsonResponse
    {
        $log = WebhookRequest::create([
            'provider' => 'discord',
            'requester_id' => $request->input('guild_id', 'unknown'),
            'requester_type' => 'guild',
            'payload_in' => $request->all(),
            'action' => $request->input('data.name', 'unknown'),
            'status' => 'pending',
        ]);

        if (! $this->provider->verify($request)) {
            $log->update(['status' => 'unauthorized']);

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        if ($request->input('type') === 1) {
            $log->update(['status' => 'ping', 'payload_out' => ['type' => 1]]);

            return response()->json(['type' => 1]);
        }

        $topic = $request->input('data.name');
        $post = $selector->randomForTopic($topic);

        // Let the provider handle formatting the post or error message
        $payload = $this->provider->formatPayload($post, $topic);

        $log->update([
            'status' => $post instanceof FeedPost ? 'success' : 'failed',
            'payload_out' => $payload,
        ]);

        return $this->provider->respond($payload);
    }
}
