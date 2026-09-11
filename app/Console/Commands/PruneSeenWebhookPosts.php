<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\WebhookSentPost;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Delete webhook_sent_posts rows older than the configured retention window, so previously-sent posts can resurface again over time.')]
#[Signature('webhook:prune-seen')]
class PruneSeenWebhookPosts extends Command
{
    public function handle(): int
    {
        $days = (int) config('services.webhook.seen_expiry_days', 30);

        $deleted = WebhookSentPost::where('sent_at', '<', now()->subDays($days))->delete();

        $this->info("Pruned {$deleted} webhook_sent_posts row(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
