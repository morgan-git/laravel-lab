<x-layout>
<div class="p-6" x-data="{ activeImage: null, activeTitle: '', activeContent: '', activeUrl: '#' }">

    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6 gap-4">
        <h1 class="text-2xl font-bold capitalize">
            @if ($topic)
                {{ $topic }}
            @elseif ($provider && $handle)
                {{ $provider }} — {{ $handle }}
            @else
                Content Hub
            @endif
        </h1>

        <div class="flex items-center">
            <select class="select select-bordered select-sm w-full md:w-64" onchange="if (this.value) window.location.href=this.value">
                <option value="{{ route('feed.index') }}">Filter by Source...</option>
                @foreach ($sources as $source)
                    <option
                        value="{{ route('feed.index', ['provider' => $source['provider'], 'handle' => $source['handle']]) }}"
                        @selected($provider === $source['provider'] && $handle === $source['handle'])
                    >
                        {{ ucfirst($source['provider']) }} — {{ $source['display_name'] }} ({{ $source['posts_count'] }})
                    </option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach ($posts as $post)
            @php $source = $sources[$post['feed_source_id']] ?? null; @endphp

            <div class="card bg-base-200 shadow-md overflow-hidden flex flex-col justify-between">
                @if ($post['image_url'])
                    <div
                        class="relative cursor-pointer group overflow-hidden"
                        @click="activeImage = @js($post['image_url']);
                                activeTitle = @js($post['title']);
                                activeContent = @js($post['content'] ?? $post['title']);
                                activeUrl = @js($post['url'])"
                    >
                        <img
                            src="{{ $post['image_url'] }}"
                            class="w-full h-80 object-cover transition-transform duration-300 group-hover:scale-105"
                            alt="{{ $post['title'] }}"
                        >

                        <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center text-white text-sm font-medium">
                            Click to expand
                        </div>

                        @if ($source)
                            <x-provider-icon :provider="$source['provider']" />
                        @endif
                    </div>
                @endif

                <div class="card-body p-4 flex flex-col justify-between flex-grow">
                    <a href="{{ $post['url'] }}" target="_blank" class="card-title text-base hover:text-primary line-clamp-2">
                        {{ $post['title'] }}
                    </a>

                    <div class="text-xs text-base-content/60 mt-2">
                        {{ $post['author'] }} · {{ $post['posted_at'] ? \Carbon\Carbon::parse($post['posted_at'])->diffForHumans() : '' }}
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div
        x-show="activeImage"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/85 p-4"
        @keydown.escape.window="activeImage = null"
        @click.self="activeImage = null"
    >
        <div class="relative max-w-4xl w-full max-h-[90vh] bg-base-100 rounded-lg overflow-hidden shadow-2xl flex flex-col md:flex-row">
            <button @click="activeImage = null" class="absolute top-3 right-3 btn btn-circle btn-sm btn-neutral z-10">
                ✕
            </button>

            <div class="md:w-3/5 bg-black flex items-center justify-center p-2 relative group cursor-pointer">
                <a :href="activeUrl" target="_blank" class="block w-full h-full flex items-center justify-center relative">
                    <img :src="activeImage" :alt="activeTitle" class="max-h-[75vh] w-auto object-contain">
                    <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center text-white text-sm font-medium">
                        Open Source in New Window ↗
                    </div>
                </a>
            </div>

            <div class="md:w-2/5 p-6 bg-base-200 flex flex-col justify-between overflow-y-auto max-h-[75vh]">
                <div>
                    <h3 class="text-xl font-bold mb-3 text-base-content" x-text="activeTitle"></h3>
                    <p class="text-sm text-base-content/80 whitespace-pre-line leading-relaxed" x-text="activeContent"></p>
                </div>

                <div class="mt-6 pt-4 border-t border-base-300">
                    <a :href="activeUrl" target="_blank" class="btn btn-primary btn-block">
                        Open Original Post ↗
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
</x-layout>
