<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FeedPost;
use App\Models\FeedSource;
use Illuminate\Support\Facades\Cache;

class FeedController extends Controller
{
    public function index(?string $provider = null, ?string $handle = null)
    {
        $sources = $this->cachedActiveSources();

        $postsCacheKey = 'feed_posts_'.($provider ?? 'all').'_'.($handle ?? 'all');

        $posts = Cache::remember(
            $postsCacheKey,
            now()->addMinutes(30),
            fn () => FeedPost::whereHas('source', function ($query) use ($provider, $handle) {
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
