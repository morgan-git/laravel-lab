<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncFeedSource;
use App\Models\FeedSource;
use App\Models\Topic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FeedSourceController extends Controller
{
    /**
     * Providers currently registered as tagged FeedProvider bindings.
     * Keep this list in sync with AppServiceProvider's $feedProviders array.
     */
    private const array AVAILABLE_PROVIDERS = ['reddit', 'bluesky', 'tumblr'];

    /**
     * Cache key the public feed page uses for its list of visible sources.
     * Lives on the FeedSource model as the single source of truth —
     * referenced here so this key can never drift out of sync with it.
     */
    private const string SOURCES_CACHE_KEY = FeedSource::VISIBLE_CACHE_KEY;

    public function index(): View
    {
        $sources = FeedSource::with('topic')
            ->withCount('posts')
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
            'topics' => Topic::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateSource($request);
        $validated['topic_id'] = $this->resolveTopicId($request);
        unset($validated['new_topic']);

        FeedSource::create($validated);

        Cache::forget(self::SOURCES_CACHE_KEY);

        return redirect()
            ->route('admin.feed-sources.index')
            ->with('status', "Added {$validated['provider']}/{$validated['handle']}.");
    }

    public function edit(FeedSource $feedSource): View
    {
        return view('admin.feed-sources.edit', [
            'source' => $feedSource,
            'providers' => self::AVAILABLE_PROVIDERS,
            'topics' => Topic::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, FeedSource $feedSource): RedirectResponse
    {
        $validated = $this->validateSource($request, $feedSource);
        $validated['topic_id'] = $this->resolveTopicId($request);
        unset($validated['new_topic']);

        $feedSource->update($validated);

        Cache::forget(self::SOURCES_CACHE_KEY);

        return redirect()
            ->route('admin.feed-sources.index')
            ->with('status', "Updated {$feedSource->provider}/{$feedSource->handle}.");
    }

    public function destroy(FeedSource $feedSource): RedirectResponse
    {
        $label = "{$feedSource->provider}/{$feedSource->handle}";
        $feedSource->delete();

        Cache::forget(self::SOURCES_CACHE_KEY);

        return redirect()
            ->route('admin.feed-sources.index')
            ->with('status', "Removed {$label}.");
    }

    public function toggle(FeedSource $feedSource): RedirectResponse
    {
        $feedSource->update(['active' => ! $feedSource->active]);

        $state = $feedSource->active ? 'activated' : 'paused';

        // 'active' only controls syncing, not public visibility, but the
        // sources cache also carries active/paused state for the admin
        // list — safe and cheap to just invalidate it here too.
        Cache::forget(self::SOURCES_CACHE_KEY);

        return redirect()
            ->route('admin.feed-sources.index')
            ->with('status', "{$feedSource->handle} {$state}.");
    }

    public function toggleVisibility(FeedSource $feedSource): RedirectResponse
    {
        $feedSource->update(['visible' => ! $feedSource->visible]);

        $state = $feedSource->visible ? 'shown' : 'hidden';

        Cache::forget(self::SOURCES_CACHE_KEY);

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
            'topic_id' => ['nullable', 'exists:topics,id'],
            'new_topic' => ['nullable', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
            'visible' => ['sometimes', 'boolean'],
        ]);
    }

    /**
     * Either use the topic picked from the dropdown, or — if the user
     * typed a brand-new topic name instead — find or create it. Keeps
     * topics normalized (no free-text typos) without needing a separate
     * topics-management page just to add the first few.
     */
    private function resolveTopicId(Request $request): ?int
    {
        if ($request->filled('new_topic')) {
            return Topic::firstOrCreate(['name' => trim((string) $request->input('new_topic'))])->id;
        }

        return $request->input('topic_id') ?: null;
    }
}
