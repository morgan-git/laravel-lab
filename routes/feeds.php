<?php

declare(strict_types=1);

use App\Http\Controllers\FeedController;
use App\Http\Controllers\RedditController;
use Illuminate\Support\Facades\Route;

Route::get('/reddit/{subreddit?}', [RedditController::class, 'index'])->name('reddit.index');

Route::get('/feed/{provider?}/{handle?}', [FeedController::class, 'index'])->name('feed.index');
