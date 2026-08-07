{{--
    Shared form fields for creating/editing a feed source.
    Included by both create.blade.php and edit.blade.php.
    Expects: $providers (array of strings), and optionally $source (FeedSource) when editing.
--}}
<div class="form-control mb-4">
    <label class="label" for="provider">
        <span class="label-text">Provider</span>
    </label>
    <select name="provider" id="provider" class="select select-bordered" required>
        @foreach ($providers as $provider)
            <option
                value="{{ $provider }}"
                @selected(old('provider', $source->provider ?? null) === $provider)
            >
                {{ ucfirst($provider) }}
            </option>
        @endforeach
    </select>
    @error('provider')
        <span class="text-error text-sm mt-1">{{ $message }}</span>
    @enderror
</div>

<div class="form-control mb-4">
    <label class="label" for="handle">
        <span class="label-text">Handle</span>
        <span class="label-text-alt">e.g. "memes" for r/memes</span>
    </label>
    <input
        type="text"
        name="handle"
        id="handle"
        class="input input-bordered"
        value="{{ old('handle', $source->handle ?? '') }}"
        required
    >
    @error('handle')
        <span class="text-error text-sm mt-1">{{ $message }}</span>
    @enderror
</div>

<div class="form-control mb-4">
    <label class="label" for="display_name">
        <span class="label-text">Display name</span>
    </label>
    <input
        type="text"
        name="display_name"
        id="display_name"
        class="input input-bordered"
        value="{{ old('display_name', $source->display_name ?? '') }}"
        required
    >
    @error('display_name')
        <span class="text-error text-sm mt-1">{{ $message }}</span>
    @enderror
</div>

<div class="form-control mb-6">
    <label class="label cursor-pointer justify-start gap-3">
        <input
            type="checkbox"
            name="active"
            value="1"
            class="checkbox"
            @checked(old('active', $source->active ?? true))
        >
        <span class="label-text">Active</span>
    </label>
</div>
