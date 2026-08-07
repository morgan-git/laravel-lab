<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FeedPost;
use Illuminate\Support\Facades\Cache;

class FeedController extends Controller
{
    public function index(string $provider = 'bluesky', string $handle = 'food-porn.bsky.social')
    {
        $posts = Cache::remember(
            "{$provider}_{$handle}",
            now()->addMinutes(30),
            fn () => FeedPost::whereHas(
                'source',
                fn ($q) => $q
                    ->where('provider', $provider)
                    ->where('handle', $handle)
            )
                ->orderByDesc('posted_at')
                ->get()
                ->toArray()
        );

        return view('feed.index', ['posts' => collect($posts), 'provider' => $provider, 'handle' => $handle]);
    }
}
