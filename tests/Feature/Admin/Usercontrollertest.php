<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// The `view-admin` Gate checks $user->is_admin (confirmed).
function actingAsAdmin(): User
{
    return User::factory()->create(['is_admin' => true]);
}

function actingAsRegularUser(): User
{
    return User::factory()->create(['is_admin' => false]);
}

// --- Access control -------------------------------------------------------

it('forbids non-admin users from the users index', function () {
    $this->actingAs(actingAsRegularUser())
        ->get(route('admin.users.index'))
        ->assertNotFound();
});

it('allows admins to view the users index', function () {
    $this->actingAs(actingAsAdmin())
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertViewIs('admin.users.index')
        ->assertViewHas('users');
});

// --- index ------------------------------------------------------------

it('lists users ordered by name', function () {
    actingAsAdmin(); // logged-in admin, plus two more below
    User::factory()->create(['name' => 'Zed']);
    User::factory()->create(['name' => 'Amy']);

    $admin = User::factory()->create(['name' => 'Morgan', 'is_admin' => true]);

    $response = $this->actingAs($admin)->get(route('admin.users.index'));

    $names = $response->viewData('users')->pluck('name')->all();
    expect($names)->toBe(collect($names)->sort()->values()->all());
});

// --- toggleAdmin ------------------------------------------------------------

it('grants admin access to a regular user', function () {
    $admin = actingAsAdmin();
    $target = User::factory()->create(['is_admin' => false]);

    $response = $this->actingAs($admin)->patch(route('admin.users.toggle-admin', $target));

    $response->assertRedirect(route('admin.users.index'));
    $response->assertSessionHas('status', "Admin access granted for {$target->name}.");
    expect($target->fresh()->is_admin)->toBeTrue();
});

it('revokes admin access from another admin', function () {
    $admin = actingAsAdmin();
    $otherAdmin = User::factory()->create(['is_admin' => true]);

    $response = $this->actingAs($admin)->patch(route('admin.users.toggle-admin', $otherAdmin));

    $response->assertRedirect(route('admin.users.index'));
    expect($otherAdmin->fresh()->is_admin)->toBeFalse();
});

it('prevents the sole admin from revoking their own admin access', function () {
    $onlyAdmin = actingAsAdmin();

    $response = $this->actingAs($onlyAdmin)->patch(route('admin.users.toggle-admin', $onlyAdmin));

    $response->assertRedirect(route('admin.users.index'));
    $response->assertSessionHas('status', "Can't remove admin from yourself — you're the only admin.");
    expect($onlyAdmin->fresh()->is_admin)->toBeTrue();
});

it('allows an admin to revoke their own access if another admin exists', function () {
    $admin = actingAsAdmin();
    actingAsAdmin(); // a second admin, so $admin is no longer the sole one

    $response = $this->actingAs($admin)->patch(route('admin.users.toggle-admin', $admin));

    $response->assertRedirect(route('admin.users.index'));
    expect($admin->fresh()->is_admin)->toBeFalse();
});
