<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FeedPost;
use App\Services\RedditService;
use Illuminate\Support\Facades\Cache;

class RedditController extends Controller
{
    public function index(string $subreddit = 'foodporn')
    {
        if (! in_array($subreddit, RedditService::ALLOWED_SUBREDDITS)) {
            $subreddit = 'foodporn';
        }

        $posts = Cache::remember(
            RedditService::CACHE_PREFIX.$subreddit,
            now()->addMinutes(30),
            fn () => FeedPost::whereHas(
                'source',
                fn ($q) => $q
                    ->where('provider', 'reddit')
                    ->where('handle', $subreddit)
            )
                ->orderByDesc('posted_at')
                ->get()
                ->toArray()
        );

        return view('reddit.index', ['posts' => collect($posts), 'subreddit' => $subreddit]);
    }
}
