<x-layout>
<div class="p-6" x-data="{ activeImage: null, activeTitle: '', activeContent: '', activeUrl: '#' }">

    <!-- Top Flex Container: Pushes title left and dropdown right on medium screens -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6 gap-4">
        <h1 class="text-2xl font-bold capitalize">
            @if(isset($provider) && isset($handle))
                {{ $provider }} — {{ $handle }}
            @else
                Content Hub
            @endif
        </h1>

        <!-- Source / Handle Filter Dropdown -->
        <div class="flex items-center">
            <select class="select select-bordered select-sm w-full md:w-64" onchange="if (this.value) window.location.href=this.value">
                <option value="{{ route('feed.index') }}">Filter by Source...</option>
                @foreach($availableSources as $source)
                    <option value="{{ route('feed.index', ['provider' => $source->provider, 'handle' => $source->handle]) }}"
                            @if(isset($provider) && isset($handle) && $provider === $source->provider && $handle === $source->display_name) selected @endif>
                        {{ ucfirst($source->provider) }} — {{ $source->display_name }} ({{ $source->posts_count }})
                    </option>
                @endforeach
            </select>
        </div>
    </div>

    <!-- Responsive Grid Layout -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($posts as $post)
            <div class="card bg-base-200 shadow-md overflow-hidden flex flex-col justify-between">
                @if(isset($post['image_url']))
                    <!-- Using json_encode safely passes titles/content with apostrophes/quotes without breaking JS -->
                    <div class="relative cursor-pointer group overflow-hidden"
                         @click="activeImage = {{ json_encode($post['image_url']) }};
                                 activeTitle = {{ json_encode($post['title'] ?? '') }};
                                 activeContent = {{ json_encode($post['content'] ?? $post['title'] ?? '') }};
                                 activeUrl = {{ json_encode($post['url'] ?? '#') }}">

                        <img src="{{ $post['image_url'] }}"
                             class="w-full h-80 object-cover transition-transform duration-300 group-hover:scale-105"
                             alt="{{ $post['title'] ?? 'Feed Image' }}">

                        <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center text-white text-sm font-medium">
                            Click to expand
                        </div>
                    </div>
                @endif

                <div class="card-body p-4 flex flex-col justify-between flex-grow">
                    <a href="{{ $post['url'] ?? '#' }}" target="_blank" class="card-title text-base hover:text-primary line-clamp-2">
                        {{ $post['title'] ?? 'Untitled' }}
                    </a>

                    <div class="text-xs text-base-content/60 mt-2">
                        {{ $post['author'] ?? $handle ?? 'Unknown' }} · {{ isset($post['updated_at']) ? \Carbon\Carbon::parse($post['updated_at'])->diffForHumans() : '' }}
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- ALPINE.JS FULLSCREEN OVERLAY MODAL -->
    <div x-show="activeImage"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/85 p-4"
         @keydown.escape.window="activeImage = null"
         @click.self="activeImage = null">

        <div class="relative max-w-4xl w-full max-h-[90vh] bg-base-100 rounded-lg overflow-hidden shadow-2xl flex flex-col md:flex-row">
            <!-- Close Button -->
            <button @click="activeImage = null" class="absolute top-3 right-3 btn btn-circle btn-sm btn-neutral z-10">
                ✕
            </button>

            <!-- Modal Left: Full-Size Image linked to source -->
            <div class="md:w-3/5 bg-black flex items-center justify-center p-2 relative group cursor-pointer">
                <a :href="activeUrl" target="_blank" class="block w-full h-full flex items-center justify-center relative">
                    <img :src="activeImage" :alt="activeTitle" class="max-h-[75vh] w-auto object-contain">
                    <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center text-white text-sm font-medium">
                        Open Source in New Window ↗
                    </div>
                </a>
            </div>

            <!-- Modal Right: Details & Full Description -->
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
