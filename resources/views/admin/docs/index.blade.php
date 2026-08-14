
<x-layout title="Docs">
    <div class="max-w-3xl mx-auto py-8 px-4">
        <h1 class="text-2xl font-bold mb-6">Docs</h1>

        @if ($docs->isEmpty())
            <p class="text-base-content/60">No docs found — nothing in the repo root or docs/ matched.</p>
        @else
            <ul class="menu bg-base-200 rounded-box w-full">
                @foreach ($docs as $doc)
                    <li>
                        <a href="{{ route('admin.docs.show', $doc['slug']) }}" class="flex justify-between items-center">
                            <span class="font-medium">{{ $doc['title'] }}</span>
                            <span class="text-xs text-base-content/50">
                                Updated {{ \Illuminate\Support\Carbon::createFromTimestamp($doc['updatedAt'])->diffForHumans() }}
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-layout>
