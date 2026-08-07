<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncFeedSource;
use App\Models\FeedSource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FeedSourceController extends Controller
{
    /**
     * Providers currently registered as tagged FeedProvider bindings.
     * Keep this list in sync with AppServiceProvider's $feedProviders array.
     */
    private const array AVAILABLE_PROVIDERS = ['reddit', 'bluesky', 'tumblr'];

    public function index(): View
    {
        $sources = FeedSource::withCount('posts')
            ->orderBy('provider')
            ->orderBy('handle')
            ->get();

        return view('admin.feed-sources.index', [
            'sources' => $sources,
        ]);
    }

    public function create(): View
    {
        return view('admin.feed-sources.create', [
            'providers' => self::AVAILABLE_PROVIDERS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateSource($request);

        FeedSource::create($validated);

        return redirect()
            ->route('admin.feed-sources.index')
            ->with('status', "Added {$validated['provider']}/{$validated['handle']}.");
    }

    public function edit(FeedSource $feedSource): View
    {
        return view('admin.feed-sources.edit', [
            'source' => $feedSource,
            'providers' => self::AVAILABLE_PROVIDERS,
        ]);
    }

    public function update(Request $request, FeedSource $feedSource): RedirectResponse
    {
        $validated = $this->validateSource($request, $feedSource);

        $feedSource->update($validated);

        return redirect()
            ->route('admin.feed-sources.index')
            ->with('status', "Updated {$feedSource->provider}/{$feedSource->handle}.");
    }

    public function destroy(FeedSource $feedSource): RedirectResponse
    {
        $label = "{$feedSource->provider}/{$feedSource->handle}";
        $feedSource->delete();

        return redirect()
            ->route('admin.feed-sources.index')
            ->with('status', "Removed {$label}.");
    }

    public function toggle(FeedSource $feedSource): RedirectResponse
    {
        $feedSource->update(['active' => ! $feedSource->active]);

        $state = $feedSource->active ? 'activated' : 'paused';

        return redirect()
            ->route('admin.feed-sources.index')
            ->with('status', "{$feedSource->handle} {$state}.");
    }

    /**
     * Manually dispatch a sync for this one source, instead of waiting
     * for the next scheduled feeds:sync run.
     */
    public function sync(FeedSource $feedSource): RedirectResponse
    {
        SyncFeedSource::dispatch($feedSource);

        return redirect()
            ->route('admin.feed-sources.index')
            ->with('status', "Sync queued for {$feedSource->handle}.");
    }

    private function validateSource(Request $request, ?FeedSource $ignoring = null): array
    {
        return $request->validate([
            'provider' => ['required', 'string', Rule::in(self::AVAILABLE_PROVIDERS)],
            'handle' => [
                'required',
                'string',
                'max:255',
                Rule::unique('feed_sources', 'handle')
                    ->where(fn ($query) => $query->where('provider', $request->input('provider')))
                    ->ignore($ignoring?->id),
            ],
            'display_name' => ['required', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
        ]);
    }
}
