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
        $cacheKey = 'feed_'.($provider ?? 'all').'_'.($handle ?? 'all');
        $availableSources = FeedSource::where('visible', 1)
            ->withCount('posts')
            ->orderBy('provider')
            ->orderBy('handle')
            ->get();
        $posts = Cache::remember(
            $cacheKey,
            now()->addMinutes(30),
            fn () => FeedPost::whereHas('source', function ($q) use ($provider, $handle) {
                if ($provider) {
                    $q->where('provider', $provider);
                }

                if ($handle) {
                    $q->where('handle', $handle);
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
            'availableSources' => $availableSources,
        ]);
    }
}
