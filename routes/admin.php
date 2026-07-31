<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\FeedSourceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:view-admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('feed-sources', FeedSourceController::class)->except(['show']);
    Route::patch('feed-sources/{feedSource}/toggle', [FeedSourceController::class, 'toggle'])->name('feed-sources.toggle');
    Route::post('feed-sources/{feedSource}/sync', [FeedSourceController::class, 'sync'])->name('feed-sources.sync');
});
