<x-layout>
    <div
        class="max-w-5xl mx-auto py-8 px-4"
        x-data="feedSourcesTable(@js($sources))"
    >
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

    <div class="rounded-box border border-base-300 overflow-visible mb-12">
    <table class="table w-full">
                <thead>
                    <tr>
                        <th>
                            <button
                                type="button"
                                @click="sortBy('provider')"
                                class="flex items-center gap-1 font-bold cursor-pointer"
                            >
                                Provider
                                <span x-text="sortIcon('provider')"></span>
                            </button>
                        </th>
                        <th>
                            <button
                                type="button"
                                @click="sortBy('handle')"
                                class="flex items-center gap-1 font-bold cursor-pointer"
                            >
                                Handle
                                <span x-text="sortIcon('handle')"></span>
                            </button>
                        </th>
                        <th>
                            <button
                                type="button"
                                @click="sortBy('display_name')"
                                class="flex items-center gap-1 font-bold cursor-pointer"
                            >
                                Display name
                                <span x-text="sortIcon('display_name')"></span>
                            </button>
                        </th>
                        <th>
                            <button
                                type="button"
                                @click="sortBy('topic')"
                                class="flex items-center gap-1 font-bold cursor-pointer"
                            >
                                Topic
                                <span x-text="sortIcon('topic')"></span>
                            </button>
                        </th>
                        <th>
                            <button
                                type="button"
                                @click="sortBy('active')"
                                class="flex items-center gap-1 font-bold cursor-pointer"
                            >
                                Status
                                <span x-text="sortIcon('active')"></span>
                            </button>
                        </th>
                        <th>
                            <button
                                type="button"
                                @click="sortBy('visible')"
                                class="flex items-center gap-1 font-bold cursor-pointer"
                            >
                                Visibility
                                <span x-text="sortIcon('visible')"></span>
                            </button>
                        </th>
                        <th>
                            <button
                                type="button"
                                @click="sortBy('posts_count')"
                                class="flex items-center gap-1 font-bold cursor-pointer"
                            >
                                Posts
                                <span x-text="sortIcon('posts_count')"></span>
                            </button>
                        </th>
                        <th>Last fetched</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(source, index) in sortedSources" :key="source.id">
                        <tr>
                            <td>
                                <span class="badge badge-outline" x-text="source.provider"></span>
                            </td>
                            <td x-text="source.handle"></td>
                            <td x-text="source.display_name"></td>
                            <td x-text="source.topic?.name ?? '—'"></td>
                            <td>
                                <span
                                    x-text="source.active ? 'Active' : 'Paused'"
                                    :class="source.active
                                        ? 'badge badge-soft badge-success'
                                        : 'badge badge-ghost'"
                                ></span>
                            </td>
                            <td>
                                <span
                                    x-text="source.visible ? 'Visible' : 'Hidden'"
                                    :class="source.visible
                                        ? 'badge badge-soft badge-success'
                                        : 'badge badge-ghost'"
                                ></span>
                            </td>
                            <td x-text="source.posts_count"></td>
                            <td>
                                <span :title="source.last_fetched_at" x-text="timeAgo(source.last_fetched_at)"></span>
                            </td>
                            <td class="text-right">
                                <div class="dropdown dropdown-end" :class="index >= sortedSources.length - 2 ? 'dropdown-top' : 'dropdown-bottom'">
                                    <div tabindex="0" role="button" class="btn btn-sm btn-ghost">⋮</div>
                                    <ul tabindex="0" class="dropdown-content menu bg-base-100 rounded-box border border-base-300 shadow z-10 w-40 p-2">
                                        <li>
                                            <form method="POST" :action="`{{ url('/admin/feed-sources') }}/${source.id}/sync`">
                                                @csrf
                                                <button type="submit" class="w-full text-left">Sync now</button>
                                            </form>
                                        </li>
                                        <li>
                                            <form method="POST" :action="`{{ url('/admin/feed-sources') }}/${source.id}/toggle`">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="w-full text-left" x-text="source.active ? 'Pause' : 'Activate'"></button>
                                            </form>
                                        </li>
                                        <li>
                                            <form method="POST" :action="`{{ url('/admin/feed-sources') }}/${source.id}/toggle-visibility`">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="w-full text-left" x-text="source.visible ? 'Hide' : 'Show'"></button>
                                            </form>
                                        </li>
                                        <li>
                                            <a :href="`{{ url('/admin/feed-sources') }}/${source.id}/edit`">Edit</a>
                                        </li>
                                        <li>
                                            <form
                                                method="POST"
                                                :action="`{{ url('/admin/feed-sources') }}/${source.id}`"
                                                onsubmit="return confirm('Remove feed source? This can\'t be undone.');"
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
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</x-layout>
