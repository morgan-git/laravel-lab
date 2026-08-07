<x-layout>
    <div class="max-w-7xl mx-auto py-8 px-4">
        <h1 class="text-2xl font-bold mb-6">Queue Jobs</h1>

        @if (session('status'))
            <div class="alert alert-info mb-4">
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <h2 class="text-lg font-semibold mb-3">Pending / Processing ({{ $pendingJobs->count() }})</h2>
        <div class="overflow-x-auto rounded-box border border-base-300 mb-8">
            <table class="table">
                <thead>
                    <tr>
                        <th>Job</th>
                        <th>Queue</th>
                        <th>Attempts</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pendingJobs as $job)
                        <tr>
                            <td>{{ $job->displayName() }}</td>
                            <td>{{ $job->queue }}</td>
                            <td>{{ $job->attempts }}</td>
                            <td>
                                @if ($job->isStuck())
                                    <span class="badge badge-error" title="Reserved for over 5 minutes — the worker probably crashed.">
                                        Stuck
                                    </span>
                                @elseif ($job->isReserved())
                                    <span class="badge badge-warning">Processing</span>
                                @else
                                    <span class="badge badge-ghost">Pending</span>
                                @endif
                            </td>
                            <td>
                                <span title="{{ $job->created_at }}">
                                    {{ $job->created_at->diffForHumans() }}
                                </span>
                            </td>
                            <td class="text-right">
                                <form
                                    method="POST"
                                    action="{{ route('admin.jobs.cancel', $job) }}"
                                    onsubmit="return confirm('Cancel this job? It will not run.');"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline btn-error">
                                        Cancel
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-8 text-base-content/60">
                                Nothing in the queue right now.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <h2 class="text-lg font-semibold mb-3">Failed ({{ $failedJobs->count() }})</h2>
        <div class="overflow-x-auto rounded-box border border-base-300">
            <table class="table">
                <thead>
                    <tr>
                        <th>Job</th>
                        <th>Error</th>
                        <th>Failed at</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($failedJobs as $job)
                        <tr>
                            <td>{{ $job->displayName() }}</td>
                            <td class="max-w-md">
                                <span class="text-error text-sm" title="{{ $job->exception }}">
                                    {{ \Illuminate\Support\Str::limit($job->shortException(), 100) }}
                                </span>
                            </td>
                            <td>
                                <span title="{{ $job->failed_at }}">
                                    {{ $job->failed_at->diffForHumans() }}
                                </span>
                            </td>
                            <td class="text-right">
                                <div class="flex justify-end gap-2">
                                    <form method="POST" action="{{ route('admin.jobs.retry', $job) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline">
                                            Retry
                                        </button>
                                    </form>
                                    <form
                                        method="POST"
                                        action="{{ route('admin.jobs.forget', $job) }}"
                                        onsubmit="return confirm('Permanently delete this failed job record?');"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline btn-error">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center py-8 text-base-content/60">
                                No failed jobs. 🎉
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layout>
