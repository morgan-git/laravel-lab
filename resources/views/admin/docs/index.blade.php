<x-layout title="Docs">
    <div
        x-data="{
            activeDoc: '{{ $docs->first()['slug'] ?? '' }}'
        }"
        class="h-[calc(100vh-5rem)] flex overflow-hidden"
    >

        {{-- LEFT DOCUMENT LIST --}}
        <aside class="w-72 shrink-0 border-r border-base-300 bg-base-200 overflow-y-auto">
            <div class="p-4 border-b border-base-300">
                <h1 class="text-xl font-bold">Documentation</h1>
                <p class="text-xs text-base-content/60 mt-1">
                    {{ $docs->count() }} {{ Str::plural('document', $docs->count()) }}
                </p>
            </div>

            <nav class="p-2">
                @forelse ($docs as $doc)
                    <button
                        type="button"
                        @click="activeDoc = '{{ $doc['slug'] }}'"
                        class="w-full text-left rounded-lg px-3 py-2.5 mb-1 transition-colors"
                        :class="activeDoc === '{{ $doc['slug'] }}'
                            ? 'bg-primary text-primary-content'
                            : 'hover:bg-base-300'"
                    >
                        <div class="font-medium">
                            {{ $doc['title'] }}
                        </div>

                        <div
                            class="text-xs mt-0.5"
                            :class="activeDoc === '{{ $doc['slug'] }}'
                                ? 'text-primary-content/70'
                                : 'text-base-content/50'"
                        >
                            Updated
                            {{ \Illuminate\Support\Carbon::createFromTimestamp($doc['updatedAt'])->diffForHumans() }}
                        </div>
                    </button>
                @empty
                    <p class="p-3 text-sm text-base-content/60">
                        No documentation found.
                    </p>
                @endforelse
            </nav>
        </aside>


        {{-- RIGHT DOCUMENT CONTENT --}}
        <main class="flex-1 overflow-y-auto">
            @forelse ($docs as $doc)
                <article
                    x-show="activeDoc === '{{ $doc['slug'] }}'"
                    x-cloak
                    class="max-w-5xl mx-auto px-8 py-8"
                >
                    <div class="flex justify-between items-baseline gap-4 mb-8">
                        <h2 class="text-3xl font-bold">
                            {{ $doc['title'] }}
                        </h2>

                        <span class="text-xs text-base-content/50 whitespace-nowrap">
                            Updated
                            {{ \Illuminate\Support\Carbon::createFromTimestamp($doc['updatedAt'])->diffForHumans() }}
                        </span>
                    </div>

                    <div class="prose prose-lg max-w-none">
                        {!! $doc['html'] !!}
                    </div>
                </article>
            @empty
                <div class="flex items-center justify-center h-full">
                    <p class="text-base-content/60">
                        No documentation found.
                    </p>
                </div>
            @endforelse
        </main>

    </div>
</x-layout>
