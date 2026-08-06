
<x-layout>
    <div class="max-w-5xl mx-auto py-8 px-4">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold">Feed Sources</h1>
            <a href="{{ route('admin.feed-sources.create') }}" class="btn btn-primary">
                Add source
            </a>
        </div>

        @if (session('status'))
            <div class="alert alert-success mb-4">
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <div class="overflow-x-auto rounded-box border border-base-300">
            <table class="table">
                <thead>
                    <tr>
                        <th>Provider</th>
                        <th>Handle</th>
                        <th>Display name</th>
                        <th>Status</th>
                        <th>Posts</th>
                        <th>Last fetched</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sources as $source)
                        <tr>
                            <td>
                                <span class="badge badge-outline">{{ $source->provider }}</span>
                            </td>
                            <td>{{ $source->handle }}</td>
                            <td>{{ $source->display_name }}</td>
                            <td>
                                @if ($source->active)
                                    <span class="badge badge-success">Active</span>
                                @else
                                    <span class="badge badge-ghost">Paused</span>
                                @endif
                            </td>
                            <td>{{ $source->posts_count }}</td>
                            <td>
                                @if ($source->last_fetched_at)
                                    <span title="{{ $source->last_fetched_at }}">
                                        {{ $source->last_fetched_at->diffForHumans() }}
                                    </span>
                                @else
                                    <span class="text-base-content/50">Never</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <div class="dropdown dropdown-end dropdown-bottom">
                                    <div tabindex="0" role="button" class="btn btn-sm btn-ghost">⋮</div>
                                    <ul tabindex="0" class="dropdown-content menu bg-base-100 rounded-box border border-base-300 shadow z-10 w-40 p-2">
                                        <li>
                                            <form method="POST" action="{{ route('admin.feed-sources.sync', $source) }}">
                                                @csrf
                                                <button type="submit" class="w-full text-left">Sync now</button>
                                            </form>
                                        </li>
                                        <li>
                                            <form method="POST" action="{{ route('admin.feed-sources.toggle', $source) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="w-full text-left">
                                                    {{ $source->active ? 'Pause' : 'Activate' }}
                                                </button>
                                            </form>
                                        </li>
                                        <li>
                                            <a href="{{ route('admin.feed-sources.edit', $source) }}">Edit</a>
                                        </li>
                                        <li>
                                            <form
                                                method="POST"
                                                action="{{ route('admin.feed-sources.destroy', $source) }}"
                                                onsubmit="return confirm('Remove {{ $source->handle }}? This can\'t be undone.');"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="w-full text-left text-error">Remove</button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-8 text-base-content/60">
                                No feed sources yet. Add one to get started.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layout>
