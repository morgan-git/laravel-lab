<x-layout>
    <div class="max-w-xl mx-auto py-8 px-4">
        <h1 class="text-2xl font-bold mb-6">Add Feed Source</h1>

        <form method="POST" action="{{ route('admin.feed-sources.store') }}">
            @csrf

            @include('admin.feed-sources._form')

            <div class="flex gap-3">
                <button type="submit" class="btn btn-primary">Add source</button>
                <a href="{{ route('admin.feed-sources.index') }}" class="btn btn-ghost">Cancel</a>
            </div>
        </form>
    </div>
</x-layout>
