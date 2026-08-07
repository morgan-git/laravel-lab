<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\FeedSourceController;
use App\Http\Controllers\Admin\JobController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:view-admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('feed-sources', FeedSourceController::class)->except(['show']);
    Route::patch('feed-sources/{feedSource}/toggle', [FeedSourceController::class, 'toggle'])->name('feed-sources.toggle');
    Route::post('feed-sources/{feedSource}/sync', [FeedSourceController::class, 'sync'])->name('feed-sources.sync');

    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::patch('users/{user}/toggle-admin', [UserController::class, 'toggleAdmin'])->name('users.toggle-admin');

    Route::get('jobs', [JobController::class, 'index'])->name('jobs.index');
    Route::delete('jobs/{job}', [JobController::class, 'cancel'])->name('jobs.cancel');
    Route::post('failed-jobs/{failedJob}/retry', [JobController::class, 'retry'])->name('jobs.retry');
    Route::delete('failed-jobs/{failedJob}', [JobController::class, 'forget'])->name('jobs.forget');
});
