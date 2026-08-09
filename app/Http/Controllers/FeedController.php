<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FeedPost;
use App\Models\FeedSource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class FeedController extends Controller
{
    public function index(Request $request, ?string $provider = null, ?string $handle = null)
    {
        $topic = $request->query('topic');
        $sources = $this->cachedActiveSources();

        $postsCacheKey = $topic
            ? "feed_posts_topic_{$topic}"
            : 'feed_posts_'.($provider ?? 'all').'_'.($handle ?? 'all');

        $posts = Cache::remember(
            $postsCacheKey,
            now()->addMinutes(30),
            fn () => FeedPost::whereHas('source', function ($query) use ($provider, $handle, $topic) {
                if ($topic) {
                    $query->where('visible', true)
                        ->whereHas('topic', fn ($topicQuery) => $topicQuery->where('name', $topic));

                    return;
                }

                if ($provider) {
                    $query->where('provider', $provider);
                }

                if ($handle) {
                    $query->where('handle', $handle);
                }
            })
                ->orderByDesc('posted_at')
                ->get()
                ->toArray()
        );

        return view('feed.index', [
            'posts' => collect($posts),
            'provider' => $provider,
            'handle' => $handle,
            'topic' => $topic,
            'sources' => $sources,
        ]);
    }

    /**
     * Active feed sources, keyed by id so the view can look up a post's
     * source via $sources[$post['feed_source_id']] instead of relying
     * on eager-loaded relations or data_get() fallback chains.
     */
    private function cachedActiveSources()
    {
        return FeedSource::cachedVisible()->keyBy('id');
    }
}
