<?php

declare(strict_types=1);

use App\Jobs\SyncFeedSource;
use App\Models\FailedJob;
use App\Models\QueueJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

// The `view-admin` Gate checks $user->is_admin (confirmed).
//
// QueueJob/FailedJob wrap Laravel's stock `jobs`/`failed_jobs` tables
// (confirmed default schema). No factories exist for these framework-managed
// tables, so rows are inserted directly with DB::table() below.

function makeQueueJobRow(array $overrides = []): QueueJob
{
    $id = DB::table('jobs')->insertGetId(array_merge([
        'queue' => 'default',
        'payload' => json_encode(['displayName' => SyncFeedSource::class]),
        'attempts' => 0,
        'reserved_at' => null,
        'available_at' => now()->timestamp,
        'created_at' => now()->timestamp,
    ], $overrides));

    return QueueJob::find($id);
}

function makeFailedJobRow(array $overrides = []): FailedJob
{
    $uuid = $overrides['uuid'] ?? (string) Str::uuid();

    $id = DB::table('failed_jobs')->insertGetId(array_merge([
        'uuid' => $uuid,
        'connection' => 'database',
        'queue' => 'default',
        'payload' => json_encode(['displayName' => SyncFeedSource::class]),
        'exception' => 'Exception: something broke',
        'failed_at' => now(),
    ], $overrides));

    return FailedJob::find($id);
}

// --- Access control -------------------------------------------------------

it('forbids non-admin users from the jobs index', function () {
    $this->actingAs(actingAsRegularUser())
        ->get(route('admin.jobs.index'))
        ->assertNotFound();
});

it('allows admins to view the jobs index', function () {
    $this->actingAs(actingAsAdmin())
        ->get(route('admin.jobs.index'))
        ->assertOk()
        ->assertViewIs('admin.jobs.index')
        ->assertViewHasAll(['pendingJobs', 'failedJobs']);
});

// --- index ------------------------------------------------------------

it('lists pending jobs oldest first and failed jobs newest first', function () {
    $older = makeQueueJobRow(['created_at' => now()->subMinutes(10)->timestamp]);
    $newer = makeQueueJobRow(['created_at' => now()->timestamp]);

    $olderFailure = makeFailedJobRow(['failed_at' => now()->subMinutes(10)]);
    $newerFailure = makeFailedJobRow(['failed_at' => now()]);

    $response = $this->actingAs(actingAsAdmin())->get(route('admin.jobs.index'));

    $response->assertOk();
    $response->assertViewHas('pendingJobs', fn ($jobs) => $jobs->first()->id === $older->id);
    $response->assertViewHas('failedJobs', fn ($jobs) => $jobs->first()->id === $newerFailure->id);
});

// --- cancel ------------------------------------------------------------

it('cancels a pending job by deleting its row', function () {
    $job = makeQueueJobRow();

    $this->actingAs(actingAsAdmin())
        ->delete(route('admin.jobs.cancel', $job))
        ->assertRedirect(route('admin.jobs.index'));

    $this->assertDatabaseMissing('jobs', ['id' => $job->id]);
});

// --- retry ------------------------------------------------------------

it('retries a failed job via the queue:retry artisan command', function () {
    $failedJob = makeFailedJobRow();

    Artisan::shouldReceive('call')
        ->once()
        ->with('queue:retry', ['id' => [$failedJob->uuid]])
        ->andReturn(0);

    $this->actingAs(actingAsAdmin())
        ->post(route('admin.jobs.retry', $failedJob))
        ->assertRedirect(route('admin.jobs.index'));
});

// --- forget ------------------------------------------------------------

it('forgets a failed job via the queue:forget artisan command', function () {
    $failedJob = makeFailedJobRow();

    Artisan::shouldReceive('call')
        ->once()
        ->with('queue:forget', ['id' => $failedJob->uuid])
        ->andReturn(0);

    $this->actingAs(actingAsAdmin())
        ->delete(route('admin.jobs.forget', $failedJob))
        ->assertRedirect(route('admin.jobs.index'));
});
