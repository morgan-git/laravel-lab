<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DocsController extends Controller
{
    /**
     * Docs available in the viewer: README.md and DEPLOYMENT.md at the
     * repo root, plus every .md file in docs/. Built from an actual
     * filesystem scan rather than a hardcoded list, so a new doc just
     * needs to exist on disk to show up here — no code change needed
     * to register it. Keyed by slug, which is what {doc} route params
     * get looked up against — the route param never touches a real
     * file path directly.
     *
     * @return array<string, array{title: string, path: string}>
     */
    private function availableDocs(): array
    {
        $docs = [];

        if (File::exists(base_path('README.md'))) {
            $docs['readme'] = [
                'title' => 'README',
                'path' => base_path('README.md'),
            ];
        }

        if (File::exists(base_path('DEPLOYMENT.md'))) {
            $docs['deployment'] = [
                'title' => 'Deployment',
                'path' => base_path('DEPLOYMENT.md'),
            ];
        }

        $docsPath = base_path('docs');

        if (File::isDirectory($docsPath)) {
            foreach (File::files($docsPath) as $file) {
                if ($file->getExtension() !== 'md') {
                    continue;
                }

                $slug = $file->getFilenameWithoutExtension();

                $docs[$slug] = [
                    'title' => (string) Str::of($slug)->replace('-', ' ')->title(),
                    'path' => $file->getPathname(),
                ];
            }
        }

        return $docs;
    }

    public function index(): View
    {
        $docs = collect($this->availableDocs())
            ->map(fn (array $doc, string $slug) => [
                'slug' => $slug,
                'title' => $doc['title'],
                'updatedAt' => File::lastModified($doc['path']),
            ])
            ->sortBy('title')
            ->values();

        return view('admin.docs.index', ['docs' => $docs]);
    }

    public function show(string $doc): View
    {
        $docs = $this->availableDocs();

        if (! array_key_exists($doc, $docs)) {
            throw new NotFoundHttpException("No doc named \"{$doc}\".");
        }

        $content = File::get($docs[$doc]['path']);

        return view('admin.docs.show', [
            'slug' => $doc,
            'title' => $docs[$doc]['title'],
            // html_input: strip + allow_unsafe_links: false as a defense-in-depth
            // default — these are our own committed files, but there's no
            // reason to let arbitrary embedded HTML/links execute in the
            // admin panel just because a future doc edit could introduce some.
            'html' => Str::of($content)->markdown([
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ])->toHtmlString(),
            'updatedAt' => File::lastModified($docs[$doc]['path']),
        ]);
    }
}
