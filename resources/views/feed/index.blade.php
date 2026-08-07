<x-layout>
<div class="p-6" x-data="{ activeImage: null, activeTitle: '' }">
    <h1 class="text-2xl font-bold mb-6 capitalize">{{ $provider }} — {{ $handle }}</h1>

    <!-- Responsive Grid Layout -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($posts as $post)
            <div class="card bg-base-200 shadow-md overflow-hidden flex flex-col justify-between">
                @if(isset($post['image_url']))
                    <!-- Clickable Image Trigger -->
                    <div class="relative cursor-pointer group overflow-hidden"
                         @click="activeImage = '{{ $post['image_url'] }}'; activeTitle = '{{ addslashes($post['title'] ?? '') }}'">

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
                        {{ $post['author'] ?? $handle }} · {{ isset($post['updated_at']) ? \Carbon\Carbon::parse($post['updated_at'])->diffForHumans() : '' }}
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

        <div class="relative max-w-5xl max-h-[90vh] bg-base-100 rounded-lg overflow-hidden shadow-2xl flex flex-col">
            <!-- Close Button -->
            <button @click="activeImage = null" class="absolute top-3 right-3 btn btn-circle btn-sm btn-neutral z-10">
                ✕
            </button>

            <!-- Full-Size Image Container -->
            <img :src="activeImage" :alt="activeTitle" class="max-h-[75vh] w-auto object-contain bg-black">

            <!-- Caption Footer -->
            <div class="p-4 bg-base-200 text-base-content font-medium text-center truncate" x-text="activeTitle"></div>
        </div>
    </div>
</div>
</x-layout>
