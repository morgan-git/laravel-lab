<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// --- Access control -------------------------------------------------------

it('forbids non-admin users from the docs index', function () {
    $this->actingAs(actingAsRegularUser())
        ->get(route('admin.docs.index'))
        ->assertNotFound();
});

it('allows admins to view the docs index', function () {
    $this->actingAs(actingAsAdmin())
        ->get(route('admin.docs.index'))
        ->assertOk()
        ->assertViewIs('admin.docs.index')
        ->assertViewHas('docs');
});

// --- index ------------------------------------------------------------

it('lists the README among the available docs', function () {
    // README.md is expected to always exist at the repo root — a stable
    // target to assert against without hardcoding the full doc set,
    // which will grow over time as more docs get added.
    $response = $this->actingAs(actingAsAdmin())->get(route('admin.docs.index'));

    $response->assertOk();
    $response->assertViewHas('docs', fn ($docs) => $docs->contains('slug', 'readme'));
});

// --- show ------------------------------------------------------------

it('renders a doc as HTML', function () {
    $response = $this->actingAs(actingAsAdmin())->get(route('admin.docs.show', 'readme'));

    $response->assertOk();
    $response->assertViewIs('admin.docs.show');
    $response->assertViewHas('html');

    // Confirm markdown actually got converted, not just passed through —
    // the README's real first heading is "# afterthesyntax", which
    // should come through as a rendered <h1>, not literal "# " text.
    $html = (string) $response->viewData('html');
    expect($html)->toContain('<h1>')
        ->and($html)->not->toContain('# afterthesyntax');
});

it('404s for a doc slug that does not exist', function () {
    $this->actingAs(actingAsAdmin())
        ->get(route('admin.docs.show', 'not-a-real-doc'))
        ->assertNotFound();
});

it('forbids non-admin users from viewing a doc', function () {
    $this->actingAs(actingAsRegularUser())
        ->get(route('admin.docs.show', 'readme'))
        ->assertNotFound();
});
