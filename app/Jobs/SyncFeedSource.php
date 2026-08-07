<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\FeedProvider;
use App\Models\FeedPost;
use App\Models\FeedSource;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncFeedSource implements ShouldQueue
{
    use Queueable;

    public int $tries = 100;

    public int $maxExceptions = 3;

    public int $backoff = 60;

    public $timeout = 300;

    public function __construct(
        public readonly FeedSource $source
    ) {}

    public function handle(): void
    {
        if (! $this->source->active) {
            return;
        }

        $provider = app(FeedProvider::class.':'.$this->source->provider);

        $posts = $provider->fetch($this->source->handle);

        if ($posts->get('throttled')) {
            $this->release(300);

            return;
        }

        $lastFetched = $this->source->last_fetched_at;

        $newPosts = $posts
            ->when($lastFetched, fn ($collection) => $collection->filter(
                fn ($post) => Carbon::parse($post['updated'])->isAfter($lastFetched)
            ));

        $newPosts->each(function (array $post) {
            FeedPost::updateOrCreate(
                [
                    'feed_source_id' => $this->source->id,
                    'external_id' => $post['id'],
                ],
                [
                    'title' => $post['title'],
                    'url' => $post['url'],
                    'author' => $post['author'],
                    'image_url' => $post['image'],
                    'content' => $post['content'],
                    'posted_at' => $post['updated'],
                ]
            );
        });

        if ($newPosts->isNotEmpty()) {
            $this->source->update([
                'last_fetched_at' => now(),
            ]);
        }

        Log::info('Feed sync complete', [
            'provider' => $this->source->provider,
            'handle' => $this->source->handle,
            'fetched' => $posts->count(),
            'new' => $newPosts->count(),
        ]);
    }
}
