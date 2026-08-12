<?php

use App\Http\Controllers\Auth\RegisteredUserController;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

it('registers a user', function (): void {
    // When I visit the registration page
    visit('/register')
        ->fill('name', 'Tanka Jahari')
        ->fill('email', 'Tanka@afterthesyntax.com')
        ->fill('password', 'ShaxxBodySpray123')
        ->press('@register-button') // which register button? beware. @ indicates data-test property
        ->assertPathIs(RegisteredUserController::REDIRECT_PATH);

    // expect(User::count())->toBe(1); //generic check

    $this->assertAuthenticated();

    // expect(User::where('email', 'Tanka@afterthesyntax.com')->exists())->toBe(true);
    expect(Auth::user())->toMatchArray([
        'name' => 'Tanka Jahari',
        'email' => 'Tanka@afterthesyntax.com',
    ]);

});

it('fails to register with a duplicate email', function (): void {
    // 1. Arrange: Create the initial user in the database
    $existingUser = User::factory()->create([
        'email' => 'duplicate@example.com',
    ]);

    // 2. Act: Attempt to register a new user using the same email
    visit('/register')
        ->fill('name', 'Tanka Jahari')
        ->fill('email', 'duplicate@example.com')
        ->fill('password', 'ShaxxBodySpray123')
        ->press('@register-button') // which register button? beware. @ indicates data-test property
        ->assertPathIs('/register')
        ->assertSee('email has already been taken');

});

it('logs in a user', function (): void {
    $user = User::factory()->create([
        'password' => 'ShaxxBodySpray123',
    ]);

    visit('/login')
        ->fill('email', $user->email)
        ->fill('password', 'ShaxxBodySpray123')
        ->click('@login-button');

    $this->assertAuthenticated();

});

it('logs out a user', function (): void {
    $user = User::factory()->create([
        'password' => 'ShaxxBodySpray123',
    ]);

    $this->actingAs($user);
    $this->assertAuthenticated();

    visit('/')->click('@logout-button');
    $this->assertGuest();

});
