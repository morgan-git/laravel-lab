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
    public function handle(Request $request, string $provider, FeedSelector $selector): JsonResponse
    {
        // Log the attempt before even trying to resolve a binding, so an
        // unregistered/misspelled provider in the URL still shows up in
        // webhook_requests rather than vanishing as a bare 404 with no trace.
        $log = WebhookRequest::create([
            'provider' => $provider,
            'requester_id' => 'unknown',
            'requester_type' => 'unknown',
            'payload_in' => $request->all(),
            'action' => 'unknown',
            'status' => 'pending',
        ]);

        try {
            $webhookProvider = app(WebhookProvider::class.':'.$provider);
        } catch (\Throwable) {
            $log->update(['status' => 'unknown_provider']);

            return response()->json(['error' => 'Unknown provider'], 404);
        }

        if (! $webhookProvider->verify($request)) {
            $log->update(['status' => 'unauthorized']);

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        if ($webhookProvider->isPing($request)) {
            $response = $webhookProvider->pingResponse();

            $log->update([
                'status' => 'ping',
                'payload_out' => $response->getData(true),
            ]);

            return $response;
        }

        $topic = $webhookProvider->action($request);
        $post = $selector->randomForTopic($topic);
        $payload = $webhookProvider->formatPayload($post, $topic);

        $log->update([
            'requester_id' => $webhookProvider->requesterId($request),
            'requester_type' => $webhookProvider->requesterType($request),
            'action' => $topic,
            'status' => $post instanceof FeedPost ? 'success' : 'failed',
            'payload_out' => $payload,
        ]);

        return $webhookProvider->respond($payload);
    }
}
