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
    private WebhookProvider $provider;

    public function __construct()
    {
        $this->provider = app(WebhookProvider::class.':discord');
    }

    public function discord(Request $request, FeedSelector $selector): JsonResponse
    {
        // Log the incoming request
        $log = WebhookRequest::create([
            'provider' => 'discord',
            'requester_id' => $request->input('guild_id', 'unknown'),
            'requester_type' => 'guild',
            'payload_in' => $request->all(),
            'action' => $request->input('data.name', 'unknown'),
            'status' => 'pending',
        ]);

        // Verify Discord signature
        if (! $this->provider->verify($request)) {
            $log->update(['status' => 'unauthorized']);

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        // Discord sends a ping to verify the endpoint — must respond with type 1
        if ($request->input('type') === 1) {
            $log->update(['status' => 'ping', 'payload_out' => ['type' => 1]]);

            return response()->json(['type' => 1]);
        }

        // Get the subreddit from the slash command options if provided
        $subreddit = $request->input('data.options.0.value', 'memes');

        $post = $selector->random('reddit', $subreddit);

        if (! $post instanceof FeedPost) {
            $log->update(['status' => 'failed']);

            return $this->provider->respond('No posts found for that subreddit.');
        }

        $content = "**{$post->title}**\n{$post->url}";

        $log->update([
            'status' => 'success',
            'payload_out' => ['content' => $content],
        ]);

        return $this->provider->respond($content);
    }
}
