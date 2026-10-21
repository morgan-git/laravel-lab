<?php

use App\Contracts\FeedProvider;
use App\Contracts\WebhookProvider;
use App\Http\Controllers\Controller;
use App\Jobs\SyncFeedSource;
use App\Services\BlueSkyService;
use App\Services\PinterestService;
use App\Services\RedditService;
use App\Services\TumblrService;
use App\Webhooks\DiscordWebhookProvider;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;

arch('every feed service implements the FeedProvider contract')
    ->expect([
        TumblrService::class,
        BlueSkyService::class,
        RedditService::class,
        PinterestService::class,
    ])
    ->toImplement(FeedProvider::class);

arch('the Discord consumer implements the WebhookProvider contract')
    ->expect(DiscordWebhookProvider::class)
    ->toImplement(WebhookProvider::class);

arch('contracts are interfaces')
    ->expect('App\Contracts')
    ->toBeInterfaces();

arch('admin controllers extend the base controller and are named like controllers')
    ->expect('App\Http\Controllers\Admin')
    ->toExtend(Controller::class)
    ->toHaveSuffix('Controller');

arch('the sync job is queued')
    ->expect(SyncFeedSource::class)
    ->toImplement(ShouldQueue::class);

arch('models extend Eloquent')
    ->expect('App\Models')
    ->toExtend(Model::class);

arch('the data and service layers do not depend on the HTTP layer')
    ->expect(['App\Models', 'App\Services', 'App\Jobs'])
    ->not->toUse('App\Http');

arch('app code uses strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('no debugging helpers are left in app code')
    ->expect(['dd', 'ddd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();

arch('env() is only read inside config files')
    ->expect('env')
    ->not->toBeUsedIn('App');
