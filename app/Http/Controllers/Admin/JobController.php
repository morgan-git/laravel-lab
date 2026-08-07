<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FailedJob;
use App\Models\QueueJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

class JobController extends Controller
{
    public function index(): View
    {
        return view('admin.jobs.index', [
            'pendingJobs' => QueueJob::orderBy('created_at')->get(),
            'failedJobs' => FailedJob::orderByDesc('failed_at')->get(),
        ]);
    }

    /**
     * Remove a pending/stuck job from the queue without running it.
     * There's no built-in artisan command for this on the database
     * driver, so we just delete the row directly.
     */
    public function cancel(QueueJob $job): RedirectResponse
    {
        $name = $job->displayName();
        $job->delete();

        return redirect()
            ->route('admin.jobs.index')
            ->with('status', "Cancelled: {$name}.");
    }

    public function retry(FailedJob $failedJob): RedirectResponse
    {
        Artisan::call('queue:retry', ['id' => [$failedJob->uuid]]);

        return redirect()
            ->route('admin.jobs.index')
            ->with('status', "Retry queued for {$failedJob->displayName()}.");
    }

    public function forget(FailedJob $failedJob): RedirectResponse
    {
        $name = $failedJob->displayName();
        Artisan::call('queue:forget', ['id' => $failedJob->uuid]);

        return redirect()
            ->route('admin.jobs.index')
            ->with('status', "Deleted failed job: {$name}.");
    }
}
