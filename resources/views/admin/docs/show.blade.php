
<x-layout :title="$title">
    <div class="max-w-3xl mx-auto py-8 px-4">
        <a href="{{ route('admin.docs.index') }}" class="link link-hover text-sm text-base-content/60">&larr; All docs</a>

        <div class="flex justify-between items-baseline mt-2 mb-6">
            <h1 class="text-2xl font-bold">{{ $title }}</h1>
            <span class="text-xs text-base-content/50">
                Updated {{ \Illuminate\Support\Carbon::createFromTimestamp($updatedAt)->diffForHumans() }}
            </span>
        </div>

        <article class="prose max-w-none">
            {{ $html }}
        </article>
    </div>
</x-layout>
