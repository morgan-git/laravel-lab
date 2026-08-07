<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\FeedProvider;
use App\Contracts\WebhookProvider;
use App\Models\User;
use App\Services\BlueSkyService;
use App\Services\RedditService;
use App\Webhooks\DiscordWebhookProvider;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[\Override]
    public function register(): void
    {
        $feedProviders = [
            'reddit' => RedditService::class,
            'bluesky' => BlueSkyService::class,
            // 'youtube' => YouTubeService::class,
        ];

        foreach ($feedProviders as $name => $class) {
            $this->app->bind(FeedProvider::class.':'.$name, $class);
        }

        $webhookProviders = [
            'discord' => DiscordWebhookProvider::class,
            // 'slack' => SlackWebhookProvider::class,
        ];

        foreach ($webhookProviders as $name => $class) {
            $this->app->bind(WebhookProvider::class.':'.$name, $class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('view-admin', fn (User $user) => $user->isAdmin() ? Response::allow() : Response::denyAsNotFound());

        Model::unguard();
        Model::shouldBeStrict();
        Model::automaticallyEagerLoadRelationships();
    }
}
