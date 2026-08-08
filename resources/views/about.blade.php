<x-layout>
    <div class="max-w-3xl mx-auto py-10 px-6 prose prose-invert">
        <h1>About This Project</h1>

        <p>
            This started as a Laravel tutorial project and turned into something bigger:
            a multi-source content aggregator that pulls posts from Tumblr, Reddit, and
            Bluesky, dedupes them, filters out spam and off-topic content, and serves
            the result through a live web feed and a Discord bot.
        </p>

        <h2>What it actually does</h2>
        <p>
            Feed sources are synced on a schedule via queued background jobs. Each
            provider — Tumblr, Reddit, Bluesky — implements a shared
            <code>FeedProvider</code> contract, so adding a new source is a matter of
            writing one new service class and registering it, not touching anything
            else in the app. The same pattern applies to the Discord webhook
            integration, which verifies every incoming request with Ed25519 signature
            checking before responding.
        </p>

        <p>
            An admin dashboard handles day-to-day operation: managing feed sources,
            watching the job queue (including flagging jobs that look stuck), and
            reviewing failed jobs with their actual error output — no digging through
            server logs required.
        </p>

        <h2>Built with</h2>
        <ul>
            <li>Laravel 13, PHP 8.4</li>
            <li>Pest for testing, including a real signature-verification and webhook test suite</li>
            <li>Redis (via Predis) for caching</li>
            <li>Tailwind, DaisyUI, and Alpine.js for the frontend</li>
            <li>SQLite locally, queue-driven sync jobs, scheduled via Laravel's task scheduler</li>
        </ul>

        <h2>Why</h2>
        <p>
            I've spent 20+ years writing PHP and about a year deep in Laravel
            specifically. This project is where I've been applying that — and
            learning, the hard way sometimes, what happens when a data source you're
            relying on changes the rules out from under you mid-build.
        </p>

        <p>
            The code is public — take a look at
            <a href="https://github.com/morgan-git/laravel-lab" target="_blank" rel="noopener">
                the repository
            </a>
            if you want to see how it's put together.
        </p>
    </div>
</x-layout>
